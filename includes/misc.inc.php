<?php

include('config.inc.php');

function string_starts_with($string, $prefix)
{
    $length = strlen($prefix);
    return (substr($string, 0, $length) === $prefix);
}

function string_ends_with($string, $suffix)
{
    $length = strlen($suffix);
    if ($length == 0) {
        return true;
    }

    return (substr($string, -$length) === $suffix);
}

function get_db() {
    global $authdb;

    $db = new SQLite3($authdb, SQLITE3_OPEN_READWRITE);
    $db->exec('PRAGMA foreign_keys = 1');

    return $db;
}

function get_all_users() {
    $db = get_db();
    $r = $db->query('SELECT id, emailaddress, isadmin FROM users');
    $ret = array();
    while ($row = $r->fetchArray(SQLITE3_ASSOC)) {
        array_push($ret, $row);
    }

    return $ret;
}

function get_user_info($u) {
    $db = get_db();
    $q = $db->prepare('SELECT * FROM users WHERE emailaddress = ?');
    $q->bindValue(1, $u);
    $result = $q->execute();
    $userinfo = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    return $userinfo;
}

function user_exists($u) {
    return (bool) get_user_info($u);
}

function do_db_auth($u, $p) {
    $db = get_db();
    $q = $db->prepare('SELECT * FROM users WHERE emailaddress = ?');
    $q->bindValue(1, $u);
    $result = $q->execute();
    $userinfo = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if ($userinfo and $userinfo['password'] and (crypt($p, $userinfo['password']) === $userinfo['password'])) {
        return TRUE;
    }

    return FALSE;
}

function add_user($username, $isadmin = FALSE, $password = '') {
    if (!$password) {
        $password = bin2hex(openssl_random_pseudo_bytes(32));
    }
    if (!string_starts_with($password, '$6$')) {
        $salt = bin2hex(openssl_random_pseudo_bytes(16));
        $password = crypt($password, '$6$'.$salt);
    }

    $db = get_db();
    $q = $db->prepare('INSERT INTO users (emailaddress, password, isadmin) VALUES (?, ?, ?)');
    $q->bindValue(1, $username, SQLITE3_TEXT);
    $q->bindValue(2, $password, SQLITE3_TEXT);
    $q->bindValue(3, (int)(bool) $isadmin, SQLITE3_INTEGER);
    $ret = $q->execute();
    $db->close();

    return $ret;
}

function update_user($username, $isadmin, $password) {
    if ($password && !preg_match('/\$6\$/', $password)) {
        $salt = bin2hex(openssl_random_pseudo_bytes(16));
        $password = crypt($password, '$6$'.$salt);
    }

    $db = get_db();

    if ($password) {
        $q = $db->prepare('UPDATE users SET isadmin = ?, password = ? WHERE emailaddress = ?');
        $q->bindValue(1, (int)(bool)$isadmin, SQLITE3_INTEGER);
        $q->bindValue(2, $password, SQLITE3_TEXT);
        $q->bindValue(3, $username, SQLITE3_TEXT); 
    } else {
        $q = $db->prepare('UPDATE users SET isadmin = ? WHERE emailaddress = ?');
        $q->bindValue(1, (int)(bool)$isadmin, SQLITE3_INTEGER);
        $q->bindValue(2, $username, SQLITE3_TEXT); 
    }
    $ret = $q->execute();
    $db->close();

    return $ret;
}

function delete_user($id) {
    $db = get_db();
    $q = $db->prepare('DELETE FROM users WHERE id = ?');
    $q->bindValue(1, $id, SQLITE3_INTEGER);
    $ret = $q->execute();
    $db->close();

    return $ret;
}

function valid_user($name) {
    return ( bool ) preg_match( "/^[a-z0-9@_.-]+$/i" , $name );
}

function jtable_respond($records, $method = 'multiple', $msg = 'Undefined errormessage') {
    $jTableResult = array();
    if ($method == 'error') {
        $jTableResult['Result'] = "ERROR";
        $jTableResult['Message'] = $msg;
    } elseif ($method == 'single') {
        $jTableResult['Result'] = "OK";
        $jTableResult['Record'] = $records;
    } elseif ($method == 'delete') {
        $jTableResult['Result'] = "OK";
    } elseif ($method == 'options') {
        $jTableResult['Result'] = "OK";
        $jTableResult['Options'] = $records;
    } else {
        if (isset($_GET['jtPageSize'])) {
            $jTableResult['TotalRecordCount'] = count($records);
            $records = array_slice($records, $_GET['jtStartIndex'], $_GET['jtPageSize']);
        }
        $jTableResult['Result'] = "OK";
        $jTableResult['Records'] = $records;
        $jTableResult['RecordCount'] = count($records);
    }

    header('Content-Type: application/json');
    print json_encode($jTableResult);
    exit(0);
}

function user_template_list() {
    global $templates;

    $templatelist = array();
    foreach ($templates as $template) {
        if (is_adminuser()
            or (isset($template['owner'])
                and ($template['owner'] == get_sess_user() or $template['owner'] == 'public'))) {
            array_push($templatelist, $template);
        }
    }
    return $templatelist;
}

function user_template_names() {
    $templatenames = array('None' => 'None');
    foreach (user_template_list() as $template) {
        $templatenames[$template['name']] = $template['name'];
    }
    return $templatenames;
}

?>
