<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if(php_sapi_name() !== 'cli') {
    echo "This script is intended to be run from the command line";
} else {
    if($allowrotatelogs === TRUE) {
        $current_user['username']='<system>';
        rotatelogs();
    } else {
        jtable_respond(null, 'error', 'Invalid action');
    }
}
