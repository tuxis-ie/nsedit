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
    $db->close();

    return $groupinfo;
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
    $db->close();

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
    $db->close();

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
        $db->close();

        writelog("Deleted group " . $groupinfo['name'] . ".");
        return $ret;
    } else {
        return false;
    }
}

function valid_group($name) {
    return ( bool ) preg_match( "/^[a-z0-9@_.-]+$/i" , $name );
}

?>
