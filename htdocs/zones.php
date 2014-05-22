<?php


include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (!is_logged_in()) {
    header("Location: index.php");
    die();
}

header("Content-Type: application/json");

function _do_curl($method, $opts = null, $type = 'post') {
    global $apisid, $apiuser, $apipass, $apiip, $apiport;
    $method = preg_replace('/:serverid:/', $apisid, $method);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERPWD, "$apiuser:$apipass");
    curl_setopt($ch, CURLOPT_URL, "http://$apiip:$apiport/$method"); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    if ($opts) {
        $postdata = json_encode($opts);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    }
    if ($type == 'delete') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    }
    if ($type == 'patch') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    }

    $return = curl_exec($ch); 
    $json = json_decode($return, 1);
    if (isset($json['error'])) {
        _jtable_respond(null, 'error', 'API Responds: '.$json['error']);
    } else {
        return $return;
    }
}

function _create_record($name, $records, $input, $zoneurl) {
    global $defaults;

    $content = ($input['type'] == "TXT") ? '"'.$input['content'].'"' : $input['content'];
    array_push($records, array(
        'disabled' => false,
        'type' => $input['type'],
        'name' => $name,
        'ttl'  => $input['ttl'] ? $input['ttl'] : $defaults['ttl'],
        'priority'  => $input['priority'] ? $input['priority'] : $defaults['priority'],
        'content'   => $content));

    $patch = array();
    $patch['rrsets'] = array();
    array_push($patch['rrsets'], array(
        'comments'  => array(),
        'records'   => $records,
        'changetype'=> "REPLACE",
        'type'      => $input['type'],
        'name'      => $name));
    _do_curl($zoneurl, $patch, 'patch');

    return $records;
}

function getrecords_by_name_type($zoneurl, $name, $type) {
    $zone = json_decode(_do_curl($zoneurl), 1);
    $records = array();
    foreach ($zone['records'] as $record) {
        if ($record['name'] == $name and
            $record['type'] == $type) {
            array_push($records, $record);
        }
    }

    return $records;
}

function zonesort($a, $b) {
      return strcmp($a["name"], $b["name"]);
}

function add_db_zone($zone, $owner) {
    $db = _get_db();
    $zoneinfo = $db->querySingle("INSERT OR REPLACE INTO zones (zone, owner) VALUES ('".$zone."', (SELECT id FROM users WHERE emailaddress = '".$owner."'))");
    $db->close();
}

function get_zone_owner($zone) {
    $db = _get_db();
    $zoneinfo = $db->querySingle("SELECT u.emailaddress FROM users u, zones z WHERE z.owner = u.id AND z.zone = '".$zone."'", 1);
    $db->close();
    if (isset($zoneinfo['emailaddress']) && $zoneinfo['emailaddress'] != NULL ) {
        return $zoneinfo['emailaddress'];
    }
    
    return 'admin';
}

function check_owner($zone) {
    if (is_adminuser() === TRUE) {
        return TRUE ;
    }
    
    if (get_zone_owner($zone) == get_sess_user()) {
        return TRUE;
    }

    return FALSE;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    _jtable_respond(null, 'error', 'No action given');
}

if ($action == "list" or $action== "listslaves") {
    $rows = json_decode(_do_curl('servers/:serverid:/zones'), 1);
    $return = array();
    foreach ($rows as $zone) {
        if (check_owner($zone['name']) === FALSE)
            continue;

        $zone['owner'] = get_zone_owner($zone['name']);
        if ($action == "listslaves" and $zone['kind'] == "Slave") {
            array_push($return, $zone);
        } elseif ($action == "list" and $zone['kind'] != "Slave") {
            array_push($return, $zone);
        }
    }
    usort($return, "zonesort");
    _jtable_respond($return);
} elseif ($action == "create") {
    if ($_POST['kind'] != null and $_POST['name'] != null) {
        $nameservers = array();
        if ($_POST['kind'] != "Slave") {
            if ($_POST['nameserver1'] != null) {
                array_push($nameservers, $_POST['nameserver1']);
                if ($_POST['nameserver2'] != null) {
                    array_push($nameservers, $_POST['nameserver2']);
                }
            } else {
                _jtable_respond(null, 'error', "Not enough data: ".print_r($_POST, 1));
            }
        }
        $vars['name'] = $_POST['name'];
        $vars['kind'] = $_POST['kind'];
        $vars['nameservers'] = $nameservers;
        _do_curl('/servers/:serverid:/zones', $vars); 
        if (isset($_POST['owner']) and $_POST['owner'] != 'admin') {
            add_db_zone($vars['name'], $_POST['owner']);
        } else {
            add_db_zone($vars['name'], get_sess_user());
        }
        if (isset($_POST['template']) && $_POST['template'] != 'None') {
            foreach ($templates as $template) {
                if ($template['name'] == $_POST['template']) {
                    $zoneurl = '/servers/:serverid:/zones/'.$vars['name'].'.';
                    foreach ($template['records'] as $record) {
                        if ($record['label'] != "") {
                            $name = join('.', array($record['label'], $vars['name']));
                        } else {
                            $name = $vars['name'];
                        }
                        $records = getrecords_by_name_type($zoneurl, $name, $record['type']);
                        $records = _create_record($name, $records, $record, $zoneurl);
                    }
                }
            }
        }
        $vars = $_POST;
        $vars['serial'] = 0;
        $vars['records'] = [];
        _jtable_respond($vars, 'single');
    } else {
        _jtable_respond(null, 'error', "Not enough data: ".print_r($_POST, 1));
    }
} elseif ($action == "listrecords" && $_GET['zoneurl'] != null) {
    $rows = json_decode(_do_curl($_GET['zoneurl']), 1);
    $soa = array();
    $ns  = array();
    $mx  = array();
    $any = array();
    foreach ($rows['records'] as $idx => $record) {
        $rows['records'][$idx]['id'] = json_encode($record);
        if ($record['type'] == 'SOA') { array_push($soa, $rows['records'][$idx]); }
        elseif ($record['type'] == 'NS') { array_push($ns, $rows['records'][$idx]); }
        elseif ($record['type'] == 'MX') { array_push($mx, $rows['records'][$idx]); }
        else {
            array_push($any, $rows['records'][$idx]);
        };


    }
    $ret = array_merge($soa, $ns, $mx, $any);
    _jtable_respond($ret);
} elseif ($action == "delete") {
    _do_curl("/servers/:serverid:/zones/".$_POST['id'], array(), 'delete');
    _jtable_respond(null, 'delete');
} elseif ($action == "createrecord" or $action == "editrecord") {
    $name = (!preg_match("/\.".$_POST['domain']."\.?$/", $_POST['name'])) ? $_POST['name'].'.'.$_POST['domain'] : $_POST['name'];
    $name = preg_replace("/\.$/", "", $name);
    $records = array();
    if ($action == "createrecord") {
        $records = getrecords_by_name_type($_GET['zoneurl'], $name, $_POST['type']);
    }

    $records =_create_record($name, $records, $_POST, $_GET['zoneurl']);
    _jtable_respond($records[sizeof($records)-1], 'single');
} elseif ($action == "deleterecord") {
    $todel = json_decode($_POST['id'], 1);
    $records = getrecords_by_name_type($_GET['zoneurl'], $todel['name'], $todel['type']);
    $precords = array();

    foreach ($records as $record) {
        if (
            $record['content'] == $todel['content'] and
            $record['type']    == $todel['type'] and
            $record['prio']    == $todel['prio'] and
            $record['name']    == $todel['name']) {
            continue;
        } else {
            array_push($precords, $record);
        }
    }
    $patch = array();
    $patch['rrsets'] = array();
    array_push($patch['rrsets'], array(
        'comments'  => array(),
        'records'   => $precords,
        'changetype'=> "REPLACE",
        'type'      => $todel['type'],
        'name'      => $todel['name']));
    _do_curl($_GET['zoneurl'], $patch, 'patch');
    _jtable_respond(null, 'delete');
} elseif ($action == "update") {
    add_db_zone($_POST['name'], $_POST['owner']);
    _jtable_respond($_POST, 'single');
} else {
    _jtable_respond(null, 'error', 'No such action');
}
?>
