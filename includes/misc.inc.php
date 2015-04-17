<?php

include('config.inc.php');

$blocklogin = FALSE;

if ((!isset($apipass) or empty($apipass)) or (!isset($apiip) or empty($apiip)) or (!isset($apiport) or empty($apiport))) {
    $errormsg = "You need to configure your settings for the PowerDNS API";
    $blocklogin = TRUE;
}

if (!isset($authmethod) or !preg_match('/^(xapikey|userpass|auto)$/', $authmethod)) {
    $errormsg = "The value for \$authmethod is incorrect in your config";
    $blocklogin = TRUE;
}

if (!isset($apiproto) or !preg_match('/^http(s)?$/', $apiproto)) {
    $errormsg = "The value for \$apiproto is incorrect in your config. Did you configure it?";
    $blocklogin = TRUE;
}

if (!isset($apisslverify) or !preg_match('/^[01]$/', $apisslverify)) {
    $errormsg = "The value for \$apisslverify is incorrect in your config. Did you configure it?";
    $blocklogin = TRUE;
}

if (!isset($authdb)) {
    $errormsg = "You did not configure a value for the setting \$authdb in your config";
    $blocklogin = TRUE;
}

/* No need to change stuf below */

if (function_exists('curl_init') === FALSE) {
    $errormsg = "You need PHP Curl to run nsedit";
    $blocklogin = TRUE;
}

if (class_exists('SQLite3') === FALSE) {
    $errormsg = "You need PHP SQLite3 to run nsedit";
    $blocklogin = TRUE;
}

$defaults['defaulttype'] = ucfirst(strtolower($defaults['defaulttype']));

if (isset($authdb) && !file_exists($authdb) && class_exists('SQLite3')) {
    is_dir(dirname($authdb)) || mkdir(dirname($authdb));
    $db = new SQLite3($authdb, SQLITE3_OPEN_CREATE|SQLITE3_OPEN_READWRITE);
    $createsql = file_get_contents('includes/scheme.sql');
    $db->exec($createsql);
    $salt = bin2hex(openssl_random_pseudo_bytes(16));
    $db->exec("INSERT INTO users (emailaddress, password, isadmin) VALUES ('admin', '".crypt("admin", '$6$'.$salt)."', 1)");
}

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



/* This function was taken from https://gist.github.com/rsky/5104756 to make
it available on older php versions. Thanks! */

if (!function_exists('hash_pbkdf2')) {
    function hash_pbkdf2($algo, $password, $salt, $iterations, $length = 0, $rawOutput = false) {
        // check for hashing algorithm
        if (!in_array(strtolower($algo), hash_algos())) {
            trigger_error(sprintf(
                '%s(): Unknown hashing algorithm: %s',
                __FUNCTION__, $algo
            ), E_USER_WARNING);
            return false;
        }

        // check for type of iterations and length
        foreach (array(4 => $iterations, 5 => $length) as $index => $value) {
            if (!is_numeric($value)) {
                trigger_error(sprintf(
                    '%s() expects parameter %d to be long, %s given',
                    __FUNCTION__, $index, gettype($value)
                ), E_USER_WARNING);
                return null;
            }
        }

        // check iterations
        $iterations = (int)$iterations;
        if ($iterations <= 0) {
            trigger_error(sprintf(
                '%s(): Iterations must be a positive integer: %d',
                __FUNCTION__, $iterations
            ), E_USER_WARNING);
            return false;
        }

        // check length
        $length = (int)$length;
        if ($length < 0) {
            trigger_error(sprintf(
                '%s(): Iterations must be greater than or equal to 0: %d',
                __FUNCTION__, $length
            ), E_USER_WARNING);
            return false;
        }

        // check salt
        if (strlen($salt) > PHP_INT_MAX - 4) {
            trigger_error(sprintf(
                '%s(): Supplied salt is too long, max of INT_MAX - 4 bytes: %d supplied',
                __FUNCTION__, strlen($salt)
            ), E_USER_WARNING);
            return false;
        }

        // initialize
        $derivedKey = '';
        $loops = 1;
        if ($length > 0) {
            $loops = (int)ceil($length / strlen(hash($algo, '', $rawOutput)));
        }

        // hash for each blocks
        for ($i = 1; $i <= $loops; $i++) {
            $digest = hash_hmac($algo, $salt . pack('N', $i), $password, true);
            $block = $digest;
            for ($j = 1; $j < $iterations; $j++) {
                $digest = hash_hmac($algo, $digest, $password, true);
                $block ^= $digest;
            }
            $derivedKey .= $block;
        }

        if (!$rawOutput) {
            $derivedKey = bin2hex($derivedKey);
        }

        if ($length > 0) {
            return substr($derivedKey, 0, $length);
        }

        return $derivedKey;
    }
}

?>
