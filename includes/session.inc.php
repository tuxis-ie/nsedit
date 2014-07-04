<?php

include_once('config.inc.php');
include_once('misc.inc.php');

session_start();

function is_logged_in() {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == "true") {
        return TRUE;
    } else {
        return FALSE;
    }
}

function set_logged_in($login_user) {
    $_SESSION['logged_in'] = 'true';
    $_SESSION['username']  = $login_user;
}

function set_is_adminuser() {
    $_SESSION['is_adminuser'] = 'true';
}

function is_adminuser() {
    if (isset($_SESSION['is_adminuser']) && $_SESSION['is_adminuser'] == 'true') {
        return TRUE;
    } else {
        return FALSE;
    }
}

function get_sess_user() {
    return $_SESSION['username'];
}

function logout() {
    session_destroy();
}

function try_login() {
    if (isset($_POST['username']) and isset($_POST['password'])) {
        if (valid_user($_POST['username']) === FALSE) {
            return FALSE;
        }
        $db = get_db();
        $q = $db->prepare('SELECT * FROM users WHERE emailaddress = ?');
        $q->bindValue(1, $_POST['username']);
        $result = $q->execute();
        $userinfo = $result->fetchArray(SQLITE3_ASSOC);
        if (isset($userinfo['password']) and (crypt($_POST['password'], $userinfo['password']) == $userinfo['password'])) {
            set_logged_in($_POST['username']);
            if (isset($userinfo['isadmin']) && $userinfo['isadmin'] == 1) {
                set_is_adminuser();
            }
            return TRUE;
        }
        $db->close();
    }

    return FALSE;
}

?>
