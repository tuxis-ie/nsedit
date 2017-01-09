<?php

/*
 * Permissions.
 *
 * Set on either users or groups.
 * User permissions override any permissions on groups (as are more specific)
 * Group permissions are additive
 * "Account" renamed as "Owner" in interface, and will always have full permissions.
 *
 * Bitmask:
 *      0x01 - View
 *      0x02 - Update non-special records
 *      0x04 - Update special records
 *      0x08 - Admin (e.g. change permissions)
 *
 * The interface will use combinations of these shown in the permissionsmap below.
 *
 */

$permissionmap=array(
    '0' => 'No permissions',
    '1' => 'View Only',
    '3' => 'Update normal records',
    '7' => 'Update all records',
    '15' => 'Admin'
);

define('PERM_VIEW',0x01);
define('PERM_UPDATE',0x02);
define('PERM_UPDATESPECIAL',0x04);
define('PERM_ADMIN',0x08);

define('PERM_ALL',0xffff);


// Interface function - Return an array of permissions for the zone
function get_zone_permissions($zone) {
    $db = get_db();

    $q = $db->prepare('SELECT p.id,p.user,u.emailAddress AS uname,p."group",g.name AS gname, p.permissions FROM permissions p LEFT JOIN users u ON p.user=u.id LEFT JOIN groups g ON p."group"=g.id LEFT JOIN zones z ON p.zone=z.id WHERE z.zone=?');
    $q->bindValue(1, $zone, SQLITE3_TEXT);
    $result = $q->execute();

    $ret = array();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $row2 = array();
        $row2['id']=$row['id'];
        if($row['user']>0) {
            $row2['type']='user';
            $row2['value']=$row['uname'];
        } else {
            $row2['type']='group';
            $row2['value']=$row['gname'];
        }
        $row2['permissions']=$row['permissions'];
        array_push($ret, $row2);
    }

    return $ret;
}

// Interface function - Set permissions for a zone - either userid or groupid should be null
function set_permissions($userid,$groupid,$zone,$permissions) {
    global $permissionmap;

    $db = get_db();

    $q = $db->prepare('INSERT INTO permissions (zone,user,"group",permissions) VALUES (?,?,?,?)');
    $q->bindValue(1, get_zone_id($zone), SQLITE3_INTEGER);
    $q->bindValue(2, $userid, SQLITE3_INTEGER);
    $q->bindValue(3, $groupid, SQLITE3_INTEGER);
    $q->bindValue(4, $permissions, SQLITE3_INTEGER);

    $ret = $q->execute();

    if(!is_null($userid)) {
        $who="user " . get_user_name($userid);
    } else {
        $who="group " . get_group_name($groupid);
    }

    if($ret) {
        writelog("Added '$permissionmap[$permissions]' permissions for $who from zone $zone.");
        return $db->lastInsertRowID();
    } else {
        writelog("Failed to add permissions to zone $zone ($zoneid) for $who.");
        return null;
    }
}

// Interface function - Update permissions for a zone
function update_permissions($id,$permissions) {
    global $permissionmap;

    $db = get_db();

    $q = $db->prepare('SELECT p.permissions, u.emailAddress, g.name, z.zone FROM permissions p LEFT JOIN users u ON p.user=u.id LEFT JOIN groups g ON p."group"=g.id LEFT JOIN zones z ON p.zone=z.id WHERE p.id=?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $r = $q->execute();
    $ret = $r->fetchArray(SQLITE3_NUM);
    if($ret[1]!='') {
        $who="user " . $ret[1];
    } else {
        $who="group " . $ret[2];
    }
    $before=$ret[0];
    $zone=$ret[3];
    $q->close();

    $q = $db->prepare('UPDATE permissions SET permissions=? WHERE id=?');
    $q->bindValue(1, $permissions, SQLITE3_INTEGER);
    $q->bindValue(2, $id, SQLITE3_INTEGER);

    $ret = $q->execute();

    if($ret) {
        writelog("Permissions changed on zone $zone for $who from '$permissionmap[$before]' to '$permissionmap[$permissions]'.");
        return $db->lastInsertRowID();
    } else {
        writelog("Failed to set permissions on zone $zone for $who (permissions id $id).");
        return null;
    }
}

// Interface function - Remove permissions from a zone
function remove_permissions($id) {
    global $permissionmap;

    $db = get_db();

    $q = $db->prepare('SELECT p.permissions, u.emailAddress, g.name, z.zone FROM permissions p LEFT JOIN users u ON p.user=u.id LEFT JOIN groups g ON p."group"=g.id LEFT JOIN zones z ON p.zone=z.id WHERE p.id=?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $r = $q->execute();
    $ret = $r->fetchArray(SQLITE3_NUM);
    if($ret[1]!='') {
        $who="user " . $ret[1];
    } else {
        $who="group " . $ret[2];
    }
    $before=$ret[0];
    $zone=$ret[3];
    $q->close();

    $q = $db->prepare('DELETE FROM permissions WHERE id=?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $ret = $q->execute();

    if($ret) {
        writelog("Removed '$permissionmap[$before]' permissions for $who from zone $zone");
    } else {
        writelog("Failed to remove permissions for $who from zone $zone (permissions id $id).");
    }
    return $ret;
}

// Utility function - Return the permissions set on the zone for this user *not including any group membership*
function user_permissions($zone,$userid) {
    $db = get_db();

    $q = $db->prepare('SELECT p.permissions FROM permissions p LEFT JOIN zones z ON p.zone=z.id WHERE p.user=? AND z.zone=?');
    $q->bindValue(1, $userid, SQLITE3_INTEGER);
    $q->bindValue(2, $zone, SQLITE3_TEXT);
    $r = $q->execute();
    if($r) {
        $ret = $r->fetchArray(SQLITE3_NUM);
        return $ret[0];
    } else {
        return null;
    }
}

// Utility function - Return the permissions set on the zone for this group
function group_permissions($zone,$groupid) {
    $db = get_db();

    $q = $db->prepare('SELECT p.permissions FROM permissions p LEFT JOIN zones z ON p.zone=z.id WHERE p."group"=? AND z.zone=?');
    $q->bindValue(1, $groupid, SQLITE3_INTEGER);
    $q->bindValue(2, $zone, SQLITE3_TEXT);
    $r = $q->execute();
    if($r) {
        $ret = $r->fetchArray(SQLITE3_NUM);
        return $ret[0];
    } else {
        return null;
    }
}

// utility function - get the owner of the domain. Move to misc?
function zone_owner($zone) {
    $db = get_db();

    $q = $db->prepare('SELECT owner FROM zones WHERE zones.zone=?');
    $q->bindValue(1,$zone,SQLITE3_TEXT);
    $r = $q->execute();
    if($r) {
        $ret = $r->fetchArray(SQLITE3_NUM);
        return $ret[0];
    } else {
        return null;
    }
}

// Utility function - Return the calculated permissions for this user/zone
function permissions($zone,$userid) {
    if(is_adminuser() || ($userid == zone_owner($zone))) {
        return PERM_ALL;
    }

    $perm=user_permissions($zone,$userid);

    if(!is_null($perm)) {
        return $perm;
    } else {
        $perm=0;
        $zoneid=get_zone_id($zone);
        $db = get_db();

        $q = $db->prepare('SELECT p.permissions FROM groupmembers gm LEFT JOIN permissions p ON p."group"=gm."group" WHERE zone=? AND p."group">0 AND gm.user=?');
        $q->bindValue(1, $zoneid, SQLITE3_INTEGER);
        $q->bindValue(2, $userid, SQLITE3_INTEGER);
        $r = $q->execute();

        while ($row = $r->fetchArray(SQLITE3_NUM)) {
            $perm=$perm|$row[0];
        }
        return $perm;
    }
}

// Utility function - check a permission for current user
function check_permissions($zone,$permmask) {
    return (bool) (permissions($zone,get_user_id(get_sess_user()))&$permmask);    
}


?>
