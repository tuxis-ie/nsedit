<?php

include_once 'includes/config.inc.php';
include_once 'includes/session.inc.php';
include_once 'includes/misc.inc.php';

if (!is_csrf_safe()) {
    header('Status: 403');
    header('Location: ./index.php');
    jtable_respond(null, 'error', 'Authentication required');
}

if (!is_adminuser()) {
    header('Status: 403');
    jtable_respond(null, 'error', 'You need adminprivileges to get here');
}

if (!isset($_GET['action'])) {
    header('Status: 400');
    jtable_respond(null, 'error', 'No action given');
}

switch ($_GET['action']) {

case 'list':
    $users = get_all_users();
    jtable_respond($users);
    break;

case 'listoptions':
    $users = get_all_users();
    $retusers = [];
    foreach ($users as $user) {
        $retusers[] = [
            'DisplayText' => $user['emailaddress'],
            'Value'       => $user['emailaddress']];
    }
    jtable_respond($retusers, 'options');
    break;

case 'create':
    $emailaddress = isset($_POST['emailaddress']) ? $_POST['emailaddress'] : '';
    $isadmin = isset($_POST['isadmin']) ? $_POST['isadmin'] : '0';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!valid_user($emailaddress)) {
        jtable_respond(null, 'error', 'Please only use ^[a-z0-9@_.-]+$ for usernames');
    }

    if (!$password) {
        jtable_respond(null, 'error', 'Cannot create user without password');
    }

    if (user_exists($emailaddress)) {
        jtable_respond(null, 'error', 'User already exists');
    }

    if (add_user($emailaddress, $isadmin, $password)) {
        $result = ['emailaddress' => $emailaddress, 'isadmin' => $isadmin];
        jtable_respond($result, 'single');
    } else {
        jtable_respond(null, 'error', 'Could not create user');
    }
    break;

case 'update':
    $id = isset($_POST['id']) ? intval($_POST['id']) : '';
    $emailaddress = isset($_POST['emailaddress']) ? $_POST['emailaddress'] : '';
    $isadmin = isset($_POST['isadmin']) ? $_POST['isadmin'] : '0';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($id != '' and update_user($id, $isadmin, $password)) {
        $result = ['isadmin' => $isadmin];
        jtable_respond($result, 'single');
    } else {
        jtable_respond(null, 'error', 'Could not update user');
    }
    break;

case 'delete':
    $id = isset($_POST['id']) ? intval($_POST['id']) : '';

    if ($id != '' and delete_user($id) !== false) {
        jtable_respond(null, 'delete');
    } else {
        jtable_respond(null, 'error', 'Could not delete user');
    }
    break;

default:
    jtable_respond(null, 'error', 'Invalid action');
    break;
}
