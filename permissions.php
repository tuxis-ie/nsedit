<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (!is_csrf_safe()) {
    header('Status: 403');
    header('Location: ./index.php');
    jtable_respond(null, 'error', "Authentication required");
}

$zoneid = isset($_GET['zoneid']) ? $_GET['zoneid'] : '';

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

    if ($zoneid != '') {
        $permissions = get_zone_permissions($zoneid);
        jtable_respond($permissions);
    } else {
        jtable_respond(null, 'error', 'Zone id required');
    }
    break;

case "add":
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $value = isset($_POST['value']) ? $_POST['value'] : '';
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : '';
    $zone = isset($_GET['zoneid']) ? $_GET['zoneid'] : '';

    if ($zoneid != '') {
        if($type == 'user') {
            if (user_exists($value)) {
                $userid=get_user_id($value);
                if(!is_null(user_permissions($zone,$userid))) {
                    jtable_respond(null, 'error', "User already has permissions set for this zone");
                } elseif(!is_null($id=set_permissions($userid,null,$zone,$permissions))) {
                    $entry = array('id' => $id, 'type' => 'user', 'value' => $value, 'permissions' => $permissions);
                    jtable_respond($entry, 'single');
                } else {
                    jtable_respond(null, 'error', "Failed to set permissions");
                }
            } else {
                jtable_respond(null, 'error', "User doesn't exist");
            }
        } else {
            if (group_exists($value)) {
                $groupid=get_group_id($value);
                if(!is_null(group_permissions($zone,$groupid))) {
                    jtable_respond(null, 'error', "Group already has permissions set for this zone");
                } elseif(!is_null($id=set_permissions(null,$groupid,$zone,$permissions))) {
                    $entry = array('id' => $id, 'type' => 'group', 'value' => $value, 'permissions' => $permissions);
                    jtable_respond($entry, 'single');
                } else {
                    jtable_respond(null, 'error', "Failed to set permissions");
                }
            } else {
                jtable_respond(null, 'error', "Group doesn't exist");
            }
        }
    } else {
        jtable_respond(null, 'error', 'Zone not specified');
    }
    break;

case "remove":
    $id = isset($_POST['id']) ? $_POST['id'] : '';

    if ($id != '') {
        if(remove_permissions($id)) {
            jtable_respond(null, 'delete');
        } else {
            jtable_respond(null, 'error', "Failed to remove permissions");
        }
    } else {
        jtable_respond(null, 'error', 'ID not specified');
    }
    break;

case "update":
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $permissions = isset($_POST['permissions']) ? intval($_POST['permissions']) : 0;
    if ($id != '') {
        if(update_permissions($id,$permissions)) {
            $result = array('id' => $id, 'permissions' => $permissions);
            jtable_respond($result, 'single');
        } else {
            jtable_respond(null, 'error', 'Failed to set permissions');
        }
    } else {
        jtable_respond(null, 'error', 'ID not specified');
    }

case "autocomplete":
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    $term = isset($_GET['term']) ? $_GET['term'] : '';
    if($type == 'user') {
        $users=get_usernames_filtered($term);
        print json_encode($users);
    } else {
        $groups=get_groups_filtered($term);
        print json_encode($groups);
    }
    break;

default:
    jtable_respond(null, 'error', 'Invalid action');
    break;
}
