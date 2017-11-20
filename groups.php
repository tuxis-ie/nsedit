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
    jtable_respond(null, 'error', "You need admin privileges to get here");
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

case "listmembers":
    $groupid = isset($_GET['groupid']) ? intval($_GET['groupid']) : '';

    if ($groupid != '') {
        $groups = get_group_members($groupid);
        jtable_respond($groups);
    } else {
        jtable_respond(null, 'error', 'Could not list group members');
    }
    break;

case "addmember":
    $groupid = isset($_GET['groupid']) ? intval($_GET['groupid']) : '';
    $user = isset($_POST['user']) ? $_POST['user'] : '';

    if ($groupid != '') {
        if (user_exists($user)) {
            if(is_group_member($groupid,$user)) {
                jtable_respond(null, 'error', "User already a member of the group");
            } elseif(!is_null($id=add_group_member($groupid,$user))) {
                $entry = array('id' => $id,'user' => $user);
                jtable_respond($entry, 'single');
            } else {
                jtable_respond(null, 'error', "Failed to add user to group");
            }
        } else {
            jtable_respond(null, 'error', "User doesn't exist");
        }
    } else {
        jtable_respond(null, 'error', 'Group not specified');
    }
    break;

case "removemember":
    $id = isset($_POST['id']) ? $_POST['id'] : '';

    if ($id != '') {
        if(remove_group_member($id)) {
            jtable_respond(null, 'delete');
        } else {
            jtable_respond(null, 'error', "Failed to delete user from group");
        }
    } else {
        jtable_respond(null, 'error', 'ID not specified');
    }
    break;

default:
    jtable_respond(null, 'error', 'Invalid action');
    break;
}
