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
    $method = preg_replace('/^\/+/', '', $method);

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
        jtable_respond(null, 'error', 'API Responds: '.$json['error']);
    } else {
        return $return;
    }
}

function _valid_label($name) {
    return ( bool ) preg_match( "/^([-.a-z0-9_\/\*]+)?$/i" , $name );
}

function _create_record($name, $records, $input, $zoneurl) {
    global $defaults;

    $content = ($input['type'] == "TXT") ? '"'.$input['content'].'"' : $input['content'];

    if (_valid_label($input['name']) === FALSE) {
        jtable_respond(null, 'error', "Please only use [a-z0-9_/.-]");
    }
    if (is_ascii($content) === FALSE or is_ascii($input['name']) === FALSE) {
        jtable_respond(null, 'error', "Please only use ASCII-characters in your fields");
    }

    if (preg_match('/^TXT$/', $input['type'])) {
        $content = stripslashes($input['content']);
        $content = preg_replace('/(^"|"$)/', '', $content);
        $content = addslashes($content);
        $content = '"'.$content.'"';
    }

    array_push($records, array(
        'disabled' => false,
        'type' => $input['type'],
        'name' => $name,
        'ttl'  => isset($input['ttl']) ? $input['ttl'] : $defaults['ttl'],
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

/* This function is taken from:
http://pageconfig.com/post/how-to-validate-ascii-text-in-php and got fixed by
#powerdns */

function is_ascii( $string = '' ) {
    return ( bool ) ! preg_match( '/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f\\x80-\\xff]/' , $string );
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
    return strnatcmp($a["name"], $b["name"]);
}

function add_db_zone($zone, $owner) {
    if (valid_user($owner) === FALSE) {
        jtable_respond(null, 'error', "$owner is not a valid username");
    }
    if (_valid_label($zone) === FALSE) {
        jtable_respond(null, 'error', "$zone is not a valid zonename");
    }

    if (is_apiuser()) {
        if (!get_user_info($owner)) {
            add_user($owner);
        }
    }

    $db = get_db();
    $q = $db->prepare("INSERT OR REPLACE INTO zones (zone, owner) VALUES (?, (SELECT id FROM users WHERE emailaddress = ?))");
    $q->bindValue(1, $zone, SQLITE3_TEXT);
    $q->bindValue(2, $owner, SQLITE3_TEXT);
    $q->execute();
    $db->close();
}

function delete_db_zone($owner) {
    if (_valid_label($zone) === FALSE) {
        jtable_respond(null, 'error', "$zone is not a valid zonename");
    }
    $db = get_db();
    $q = $db->prepare("DELETE FROM zones WHERE zone = ?");
    $q->bindValue(1, $zone, SQLITE3_TEXT);
    $q->execute();
    $db->close();
}

function get_zone_owner($zone) {
    if (_valid_label($zone) === FALSE) {
        jtable_respond(null, 'error', "$zone is not a valid zonename");
    }
    $db = get_db();
    $q = $db->prepare("SELECT u.emailaddress FROM users u, zones z WHERE z.owner = u.id AND z.zone = ?");
    $q->bindValue(1, $zone, SQLITE3_TEXT);
    $result = $q->execute();
    $zoneinfo = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    if (isset($zoneinfo['emailaddress']) && $zoneinfo['emailaddress'] != NULL ) {
        return $zoneinfo['emailaddress'];
    }
    
    return 'admin';
}

function get_zone_keys($zone) {
    $ret = array();
    foreach (json_decode(_do_curl("/servers/:serverid:/zones/".$zone."/cryptokeys"), 1) as $key) {
        if (!isset($key['active']))
            continue;

        $key['dstxt'] = $zone.' IN DNSKEY '.$key['dnskey']."\n\n";

        if (isset($key['ds'])) {
            foreach ($key['ds'] as $ds) {
                $key['dstxt'] .= $zone.' IN DS '.$ds."\n";
            }
        }
        unset($key['ds']);
        $ret[] = $key;
    }

    return $ret;
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
    jtable_respond(null, 'error', 'No action given');
}

if ($action == "list" or $action== "listslaves") {
    $rows = json_decode(_do_curl('servers/:serverid:/zones'), 1);
    $return = array();
    foreach ($rows as $zone) {
        if (check_owner($zone['name']) === FALSE)
            continue;

        if (isset($_POST['domsearch'])) {
            $q = $_POST['domsearch'];
            if (!preg_match("/$q/", $zone['name']) == 1) {
                continue;
            }
        }

        $zone['name'] = htmlspecialchars($zone['name']);
        $zone['owner'] = get_zone_owner($zone['name']);
        if ($action == "listslaves" and $zone['kind'] == "Slave") {
            array_push($return, $zone);
        } elseif ($action == "list" and $zone['kind'] != "Slave") {
            if ($zone['dnssec'] == true) {
                $zone['keyinfo'] = get_zone_keys($zone['name']);
            }
            array_push($return, $zone);
        }
    }
    usort($return, "zonesort");
    jtable_respond($return);
} elseif ($action == "create") {
    if (is_adminuser() !== TRUE and $allowzoneadd !== TRUE) {
        jtable_respond(null, 'error', "You are not allowed to add zones");
    }
    if (_valid_label($_POST['name']) === FALSE) {
        jtable_respond(null, 'error', "Please only use [a-z0-9_/.-]");
    }
    if (is_ascii($_POST['name']) === FALSE) {
        jtable_respond(null, 'error', "Please only use ASCII-characters in your domainname");
    }
    if ($_POST['kind'] != null and $_POST['name'] != null) {
        $nameservers = array();
        if ($_POST['kind'] != "Slave") {
            if (isset($_POST['nameserver1']) && $_POST['nameserver1'] != null) {
                array_push($nameservers, $_POST['nameserver1']);
                if (isset($_POST['nameserver2']) && $_POST['nameserver2'] != null) {
                    array_push($nameservers, $_POST['nameserver2']);
                }
            } else {
                jtable_respond(null, 'error', "Not enough data: ".print_r($_POST, 1));
            }
            if (isset($defaults['soa_edit_api'])) {
                $vars['soa_edit_api'] = $defaults['soa_edit_api'];
            }
        }
        $vars['name'] = $_POST['name'];
        $vars['kind'] = $_POST['kind'];
        if (isset($_POST['zone'])) {
            $vars['zone'] = $_POST['zone'];
            $vars['nameservers'] = array();
        } else {
            $vars['nameservers'] = $nameservers;
        }
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
                        if (!isset($record['name'])) {
                            $record['name'] = "";
                        }
                        $records = getrecords_by_name_type($zoneurl, $name, $record['type']);
                        $records = _create_record($name, $records, $record, $zoneurl);
                    }
                }
            }
        }
        
        if (!isset($_POST['zone'])) {
            $vars = $_POST;
            $vars['serial'] = 0;
            $vars['records'] = array();
            jtable_respond($vars, 'single');
        }
        $zoneurl = '/servers/:serverid:/zones/'.$vars['name'].'.';
        if (isset($_POST['owns'])) {
            $patch = array();
            $patch['rrsets'] = array();
            array_push($patch['rrsets'], array(
                'comments'  => array(),
                'records'   => array(),
                'changetype'=> "REPLACE",
                'type'      => 'NS',
                'name'      => $vars['name']));
            _do_curl($zoneurl, $patch, 'patch');
            foreach ($nameservers as $ns) {
                $records = getrecords_by_name_type($zoneurl, $vars['name'], 'NS');
                $records = _create_record($vars['name'], $records, array('type' => 'NS', 'name' => '', 'content' => $ns), $zoneurl);
            }
        }

        $vars = _do_curl($zoneurl);
        jtable_respond($vars, 'single');
    } else {
        jtable_respond(null, 'error', "Not enough data: ".print_r($_POST, 1));
    }
} elseif ($action == "listrecords" && $_GET['zoneurl'] != null) {
    $rows = json_decode(_do_curl($_GET['zoneurl']), 1);
    $soa = array();
    $ns  = array();
    $mx  = array();
    $any = array();
    foreach ($rows['records'] as $idx => $record) {
        $rows['records'][$idx]['id'] = json_encode($record);
        $rows['records'][$idx]['name'] = htmlspecialchars($record['name']);
        if ($record['type'] == 'SOA') { array_push($soa, $rows['records'][$idx]); }
        elseif ($record['type'] == 'NS') { array_push($ns, $rows['records'][$idx]); }
        elseif ($record['type'] == 'MX') { array_push($mx, $rows['records'][$idx]); }
        else {
            array_push($any, $rows['records'][$idx]);
        };
    }
    usort($any, "zonesort");
    $ret = array_merge($soa, $ns, $mx, $any);
    jtable_respond($ret);
} elseif ($action == "delete") {
    _do_curl("/servers/:serverid:/zones/".$_POST['id'], array(), 'delete');
    $zone = preg_replace("/\.$/", "", $_POST['id']);
    delete_db_zone($zone);
    jtable_respond(null, 'delete');
} elseif ($action == "createrecord" or $action == "editrecord") {
    $name = (!preg_match("/\.".$_POST['domain']."\.?$/", $_POST['name'])) ? $_POST['name'].'.'.$_POST['domain'] : $_POST['name'];
    $name = preg_replace("/\.$/", "", $name);
    $name = preg_replace("/^\./", "", $name);
    $records = array();
    if ($action == "createrecord") {
        $records = getrecords_by_name_type($_GET['zoneurl'], $name, $_POST['type']);
    }

    $records =_create_record($name, $records, $_POST, $_GET['zoneurl']);
    jtable_respond($records[sizeof($records)-1], 'single');
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
    jtable_respond(null, 'delete');
} elseif ($action == "update") {
    add_db_zone($_POST['name'], $_POST['owner']);
    jtable_respond($_POST, 'single');
} else {
    jtable_respond(null, 'error', 'No such action');
}
?>
