<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (!is_csrf_safe()) {
    header('Status: 403');
    header('Location: ./index.php');
    jtable_respond(null, 'error', "Authentication required");
}

$zoneid = isset($_GET['zoneid']) ? intval($_GET['zoneid']) : '';

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

    if ($zoneid != '') {
        $permissions = get_zone_permissions($zoneid);
        jtable_respond($permissions);
    } else {
        jtable_respond(null, 'error', 'Could not list zone permissions');
    }
    break;

case "add":
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    $permissons = isset($_POST['permissions']) ? $_POST['permissions'] : '';

    if ($zoneid != '') {
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
        jtable_respond(null, 'error', 'Zone not specified');
    }
    break;

case "remove":

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

case "autocomplete":
    $term = isset($_GET['type']) ? $_GET['type'] : '';
    $term = isset($_GET['term']) ? $_GET['term'] : '';
    $users=get_usernames_filtered($term);
    print json_encode($users);
    break;

default:
    jtable_respond(null, 'error', 'Invalid action');
    break;
}
