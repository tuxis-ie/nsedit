<?php

// matches version in scheme.sql

$db_version=2;

// Initialise a new DB with latest version

function init_db() {
    global $authdb, $db;

    is_dir(dirname($authdb)) || mkdir(dirname($authdb));
    $db = new SQLite3($authdb, SQLITE3_OPEN_CREATE|SQLITE3_OPEN_READWRITE);
    $createsql = file_get_contents('includes/scheme.sql');
    $db->exec($createsql);
    $salt = bin2hex(openssl_random_pseudo_bytes(16));
    $db->exec("INSERT INTO users (emailaddress, password, isadmin) VALUES ('admin', '".crypt("admin", '$6$'.$salt)."', 1)");

    return $db;
}

function open_db() {
    global $authdb, $db;

    if (!isset($db)) {
        $db = new SQLite3($authdb, SQLITE3_OPEN_READWRITE);
        $db->exec('PRAGMA foreign_keys = 1');
    }

    $version = intval($db->querySingle('SELECT value FROM metadata WHERE name = "version"'));

    switch($version) {
      case 0:
        $sql = file_get_contents('includes/upgrade-0-1.sql');
        $db->exec($sql);
        writelog("Upgraded schema to version 1","system");
        // continue
      case 1: // never existed
        $sql = file_get_contents('includes/upgrade-1-2.sql');
        $db->exec($sql);
        writelog("Upgraded schema to version 2","system");
        // continue
      case $db_version:
        break;
    }

    return $db;
}

function get_db() {
    global $authdb, $db;

    if (!isset($db)) {
        $db = new SQLite3($authdb, SQLITE3_OPEN_READWRITE);
        $db->exec('PRAGMA foreign_keys = 1');
    }

    return $db;
}

?>
