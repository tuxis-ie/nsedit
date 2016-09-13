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

if ($logging !== TRUE) {
    jtable_respond(null, 'error', 'Logging is disabled');
} else {
    switch ($_GET['action']) {
    
    case "list":
        if(!empty($_POST['logfile'])) {
            if(preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{6}\.json/',$_POST['logfile']) == 1) {
                $entries=json_decode(file_get_contents($logsdirectory . "/" . $_POST['logfile']),true);
            } else {
                jtable_respond(null, 'error', "Can't find log file");
                break;
            }
        } else {
            $entries=getlogs();
        }

        if(!empty($_POST['user'])) {
            $entries=array_filter($entries,
                function ($val) {
                    return(stripos($val['user'], $_POST['user']) !== FALSE);
                }
            );
        }

        if(!empty($_POST['entry'])) {
            $entries=array_filter($entries,
                function ($val) {
                    return(stripos($val['log'], $_POST['entry']) !== FALSE);
                }
            );
        }

        jtable_respond($entries);
        break;
    
    case "export":
        if(!empty($_GET['logfile'])) {
            if(preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{6}\.json/',$_GET['logfile']) == 1) {
                $entries=json_decode(file_get_contents($logsdirectory . "/" . $_GET['logfile']),true);
            } else {
                jtable_respond(null, 'error', "Can't find log file");
                break;
            }
        } else {
            $entries=getlogs();
        }

        if(defined('JSON_PRETTY_PRINT')) {
            print json_encode($entries,JSON_PRETTY_PRINT);
        } else {
            print json_encode($entries);
        }
        break;

    case "clear":
        if($allowclearlogs === TRUE) {
            clearlogs();
        } else {
            jtable_respond(null, 'error', 'Invalid action');
        }
        break;
    case "rotate":
        if($allowrotatelogs === TRUE) {
            rotatelogs();
        } else {
            jtable_respond(null, 'error', 'Invalid action');
        }
        break;
    default:
        jtable_respond(null, 'error', 'Invalid action');
        break;
    }
}
