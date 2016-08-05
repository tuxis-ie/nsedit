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
    jtable_respond(getlogs());
    break;

case "delete":
    if ($emailaddress != '' and delete_user($emailaddress) !== FALSE) {
        jtable_respond(null, 'delete');
    } else {
        jtable_respond(null, 'error', 'Could not delete user');
    }
    break;

case "export":
    print json_encode(getlogs());
    break;

case "clear":
    clearlogs();
    break;
default:
    jtable_respond(null, 'error', 'Invalid action');
    break;
}
