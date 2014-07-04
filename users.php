<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (!is_logged_in()) {
    header("Location: index.php");
}

if (!is_adminuser()) {
    jtable_respond(null, 'error', "You need adminprivileges to get here");
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    jtable_respond(null, 'error', 'No action given');
}

if ($action == "list") {
    $users = get_all_users();
    jtable_respond($users);
} elseif ($action == "create" or $action == "update") {
    if (valid_user($_POST['emailaddress']) === FALSE) {
        jtable_respond(null, 'error', "Please only use ^[a-z0-9@_.-]+$ for usernames");
    }
    $isadmin = $_POST['isadmin'] ? $_POST['isadmin'] : '0';
    if (add_user($_POST['emailaddress'], $isadmin, $_POST['password']) !== FALSE) {
        unset($_POST['password']);
        jtable_respond($_POST, 'single');
    } else {
        jtable_respond(null, 'error', 'Could not add/change this user');
    }
} elseif ($action == "delete") {
    if (delete_user($_POST['id']) !== FALSE) {
        jtable_respond(null, 'delete');
    } else {
        jtable_respond(null, 'error', 'Could not delete this user');
    }
}

?>
