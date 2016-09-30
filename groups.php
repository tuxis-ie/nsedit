<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (!is_csrf_safe()) {
    header('Status: 403');
    header('Location: ./index.php');
    jtable_respond(null, 'error', "Authentication required");
}

if (!is_adminuser()) {
    header('Status: 403');
    jtable_respond(null, 'error', "You need adminprivileges to get here");
}

if (!isset($_GET['action'])) {
    header('Status: 400');
    jtable_respond(null, 'error', 'No action given');
}

switch ($_GET['action']) {

case "list":
    $groups = get_all_groups();
    jtable_respond($groups);
    break;

case "listoptions":
    $groups = get_all_groups();
    $retgroups = array();
    foreach ($groups as $group) {
        $retgroups[] = array(
            'DisplayText' => $group['name'] . " - " . $group['desc'],
            'Value'       => $group['name']);
    }
    jtable_respond($retgroups, 'options');
    break;

case "create":
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $desc = isset($_POST['desc']) ? $_POST['desc'] : '';

    if (!valid_group($name)) {
        jtable_respond(null, 'error', "Please only use ^[a-z0-9@_.-]+$ for group names");
    }

    if (group_exists($name)) {
        jtable_respond(null, 'error', 'Group already exists');
    }

    if (add_group($name, $desc)) {
        $result = array('name' => $name, 'desc' => $desc);
        jtable_respond($result, 'single');
    } else {
        jtable_respond(null, 'error', 'Could not create group');
    }
    break;

case "update":
    $id = isset($_POST['id']) ? intval($_POST['id']) : '';
    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $desc = isset($_POST['desc']) ? $_POST['desc'] : '';

    if ($id != '' and update_group($id, $name, $desc)) {
        $result = array('name' => $name, 'desc' => $desc);
        jtable_respond($result, 'single');
    } else {
        jtable_respond(null, 'error', 'Could not update group');
    }
    break;

case "delete":
    $id = isset($_POST['id']) ? intval($_POST['id']) : '';

    if ($id != '' and delete_group($id) !== FALSE) {
        jtable_respond(null, 'delete');
    } else {
        jtable_respond(null, 'error', 'Could not delete group');
    }
    break;

default:
    jtable_respond(null, 'error', 'Invalid action');
    break;
}
