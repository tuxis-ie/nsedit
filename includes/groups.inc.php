<?php

function get_all_groups() {
    $db = get_db();
    $r = $db->query('SELECT id, name, desc FROM groups ORDER BY name');
    $ret = array();
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        array_push($ret, $row);
    }

    return $ret;
}

function get_group_info($name) {
    $db = get_db();
    $q = $db->prepare('SELECT * FROM groups WHERE name = ?');
    $q->bindValue(1, $name);
    $result = $q->execute();
    $groupinfo = $result->fetchArray(SQLITE3_ASSOC);

    return $groupinfo;
}

function get_group_name($id) {
    $db = get_db();
    $q = $db->prepare('SELECT name FROM groups WHERE id = ?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $r = $q->execute();
    $ret = $r->fetchArray(SQLITE3_NUM);

    if($ret) {
        return $ret[0];
    } else {
        return null;
    }
}

function group_exists($name) {
    return (bool) get_group_info($name);
}

function add_group($name, $desc) {
    $db = get_db();
    $q = $db->prepare('INSERT INTO groups (name, desc) VALUES (?, ?)');
    $q->bindValue(1, $name, SQLITE3_TEXT);
    $q->bindValue(2, $desc, SQLITE3_TEXT);
    $ret = $q->execute();

    writelog("Added group $name ($desc).");
    return $ret;
}

function update_group($id, $name, $desc) {
    $db = get_db();

    $q = $db->prepare('SELECT * FROM groups WHERE id = ?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $result = $q->execute();
    $groupinfo = $result->fetchArray(SQLITE3_ASSOC);
    $q->close();
    $oldname = $groupinfo['name'];

    $q = $db->prepare('UPDATE groups SET name = ?, desc = ? WHERE id = ?');
    $q->bindValue(1, $name, SQLITE3_TEXT);
    $q->bindValue(2, $desc, SQLITE3_TEXT);
    $q->bindValue(3, $id, SQLITE3_INTEGER);
    writelog("Updating group $oldname to: $name ($desc) ");
    $ret = $q->execute();

    return $ret;
}

function delete_group($id) {
    $db = get_db();

    $q = $db->prepare('SELECT * FROM groups WHERE id = ?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $result = $q->execute();
    $groupinfo = $result->fetchArray(SQLITE3_ASSOC);
    $q->close();

    if($groupinfo) {
        $q = $db->prepare('DELETE FROM groups WHERE id = ?');
        $q->bindValue(1, $id, SQLITE3_INTEGER);
        $ret = $q->execute();

        writelog("Deleted group " . $groupinfo['name'] . ".");
        return $ret;
    } else {
        return false;
    }
}

function valid_group($name) {
    return ( bool ) preg_match( "/^[a-z0-9@_.-]+$/i" , $name );
}

function get_group_members($id) {
    $db = get_db();

    $q = $db->prepare('SELECT groupmembers.id,users.emailaddress AS user FROM groupmembers,users WHERE "group" = ? AND groupmembers.user = users.id');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $result = $q->execute();

    $ret = array();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        array_push($ret, $row);
    }

    return $ret;
}

// move to misc?
function get_user_id($user) {
    $info=get_user_info($user);
    if($info) {
        return $info['id'];
    } else {
        return null;
    }
}

function get_group_id($group) {
    $info=get_group_info($group);
    if($info) {
        return $info['id'];
    } else {
        return null;
    }
}

function is_group_member($id,$user) {
    $db = get_db();

    $q = $db->prepare('SELECT id FROM groupmembers WHERE "group" = ? AND user = ?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $q->bindValue(2, get_user_id($user), SQLITE3_INTEGER);
    $r = $q->execute();
    $ret = $r->fetchArray(SQLITE3_NUM);
    return (bool) $ret;
}

function add_group_member($id,$user) {
    $db = get_db();

    $userid=get_user_id($user);

    $q = $db->prepare('INSERT INTO groupmembers ("group", user) VALUES (?, ?)');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $q->bindValue(2, $userid, SQLITE3_INTEGER);
    $ret = $q->execute();

    if($ret) {
        writelog("Added user $user to group " . get_group_name($id) . ".");
        return $db->lastInsertRowID();
    } else {
        writelog("Failed to add user $user to group " . get_group_name($id) . ".");
        return null;
    }
}

function remove_group_member($id) {
    $db = get_db();

    $q = $db->prepare('SELECT groups.name,users.emailaddress FROM groupmembers,users,groups WHERE groupmembers.id=? AND groupmembers.user=users.id AND groupmembers."group"=groups.id');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $r = $q->execute();
    $ret = $r->fetchArray(SQLITE3_NUM);
    $group=$ret[0];
    $user=$ret[1];
    $q->close();

    $q = $db->prepare('DELETE FROM groupmembers WHERE id=?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $ret = $q->execute();

    if($ret) {
        writelog("Removed user $user from group $group.");
    } else {
        writelog("Failed to remove user $user from group $group.");
    }
    return $ret;
}

?>
