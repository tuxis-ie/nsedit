<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (!is_logged_in()) {
    header("Location: index.php");
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    _jtable_respond(null, 'error', 'No action given');
}

function _valid_user($name) {
    return ( bool ) preg_match( "/^[a-z0-9@_.-]+$/i" , $name );
}


if ($action == "list") {
    $users = get_all_users();
    _jtable_respond($users);
} elseif ($action == "create" or $action == "update") {
    if (_valid_user($_POST['emailaddress']) === FALSE) {
        _jtable_respond(null, 'error', "Please only use ^[a-z0-9@_.-]+$ for usernames");
    }
    $isadmin = $_POST['isadmin'] ? $_POST['isadmin'] : '0';
    if (add_user($_POST['emailaddress'], $isadmin, $_POST['password']) === TRUE) {
        unset($_POST['password']);
        _jtable_respond($_POST, 'single');
    } else {
        _jtable_respond(null, 'error', 'Could not add/change this user');
    }
} elseif ($action == "delete") {
    if (delete_user($_POST['id']) === TRUE) {
        _jtable_respond(null, 'delete');
    } else {
        _jtable_respond(null, 'error', 'Could not delete this user');
    }
}

?>
