<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');
include_once('includes/class/PdnsApi.php');
include_once('includes/class/Zone.php');

if (!is_csrf_safe()) {
    header('Status: 403');
    header('Location: ./index.php');
    jtable_respond(null, 'error', "Authentication required");
}

/* This function is taken from:
http://pageconfig.com/post/how-to-validate-ascii-text-in-php and got fixed by
#powerdns */

function is_ascii($string) {
    return ( bool ) ! preg_match( '/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f\\x80-\\xff]/' , $string );
}

function _valid_label($name) {
    return is_ascii($name) && ( bool ) preg_match("/^([-.a-z0-9_\/\*]+)?$/i", $name );
}

function decode_record_id($id) {
    $record = json_decode($id, 1);
    if (!$record
        || !isset($record['name'])
        || !isset($record['type'])
        || !isset($record['ttl'])
        || !isset($record['content'])
        || !isset($record['disabled'])) {
        jtable_respond(null, 'error', "Invalid record id");
    }
    return $record;
}

function compareName($a, $b) {
    $a = array_reverse(explode('.', $a));
    $b = array_reverse(explode('.', $b));
    for ($i = 0; ; ++$i) {
        if (!isset($a[$i])) {
            return isset($b[$i]) ? -1 : 0;
        } else if (!isset($b[$i])) {
            return 1;
        }
        $cmp = strnatcasecmp($a[$i], $b[$i]);
        if ($cmp) {
            return $cmp;
        }
    }
}

function zone_compare($a, $b) {
    if ($cmp = strnatcasecmp($a['name'], $b['name'])) return $cmp;
    return 0;
}

function rrtype_compare($a, $b) {
    # sort specials before everything else
    $specials = array('SOA', 'NS', 'MX');
    $spa = array_search($a, $specials, true);
    $spb = array_search($b, $specials, true);
    if ($spa === false) {
        return ($spb === false) ? strcmp($a, $b) : 1;
    } else {
        return ($spb === false) ? -1 : $spa - $spb;
    }
}

function record_compare($a, $b) {
    if ($cmp = compareName($a['name'], $b['name'])) return $cmp;
    if ($cmp = rrtype_compare($a['type'], $b['type'])) return $cmp;
    if ($cmp = strnatcasecmp($a['content'], $b['content'])) return $cmp;
    return 0;
}

function add_db_zone($zonename, $ownername) {
    if (valid_user($ownername) === false) {
        jtable_respond(null, 'error', "$ownername is not a valid username");
    }
    if (!_valid_label($zonename)) {
        jtable_respond(null, 'error', "$zonename is not a valid zonename");
    }

    if (is_apiuser() && !user_exists($ownername)) {
        add_user($ownername);
    }

    $db = get_db();
    $q = $db->prepare("INSERT OR REPLACE INTO zones (zone, owner) VALUES (?, (SELECT id FROM users WHERE emailaddress = ?))");
    $q->bindValue(1, $zonename, SQLITE3_TEXT);
    $q->bindValue(2, $ownername, SQLITE3_TEXT);
    $q->execute();
    $db->close();
}

function delete_db_zone($zonename) {
    if (!_valid_label($zonename)) {
        jtable_respond(null, 'error', "$zonename is not a valid zonename");
    }
    $db = get_db();
    $q = $db->prepare("DELETE FROM zones WHERE zone = ?");
    $q->bindValue(1, $zonename, SQLITE3_TEXT);
    $q->execute();
    $db->close();
}

function get_zone_owner($zonename, $default) {
    if (!_valid_label($zonename)) {
        jtable_respond(null, 'error', "$zonename is not a valid zonename");
    }
    $db = get_db();
    $q = $db->prepare("SELECT u.emailaddress FROM users u, zones z WHERE z.owner = u.id AND z.zone = ?");
    $q->bindValue(1, $zonename, SQLITE3_TEXT);
    $result = $q->execute();
    $zoneinfo = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    if (isset($zoneinfo['emailaddress']) && $zoneinfo['emailaddress'] != null ) {
        return $zoneinfo['emailaddress'];
    }

    return $default;
}

function check_owner($zone) {
    return is_adminuser() or ($zone->account === get_sess_user());
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    jtable_respond(null, 'error', 'No action given');
}

try {
$api = new PdnsAPI;

switch ($action) {

case "list":
case "listslaves":
    $return = Array();
    $q = isset($_POST['domsearch']) ? $_POST['domsearch'] : false;
    foreach ($api->listzones($q) as $sresult) {
        $zone = new Zone();
        $zone->parse($sresult);
        $zone->setaccount(get_zone_owner($zone->name, 'admin'));

        if (!check_owner($zone))
            continue;

        if ($action == "listslaves" and $zone->kind == "Slave") {
            array_push($return, $zone->export());
        } elseif ($action == "list" and $zone->kind != "Slave") {
            if ($zone->dnssec) {
                $zone->setkeyinfo($api->getzonekeys($zone->id));
            }
            array_push($return, $zone->export());
        }
    }
    jtable_respond($return);
    break;

case "listrecords":
    $zonedata = $api->loadzone($_GET['zoneid']);
    $zone = new Zone();
    $zone->parse($zonedata);
    $records = $zone->rrsets2records();
    foreach ($records as &$record) {
        $record['id'] = json_encode($record);
    }
    unset($record);
    usort($records, "record_compare");
    jtable_respond($records);
    break;

case "delete":
    $zone = $api->loadzone($_POST['id']);
    $api->deletezone($_POST['id']);

    delete_db_zone($zone['name']);
    jtable_respond(null, 'delete');
    break;


case "create":
    $zonename = isset($_POST['name']) ? $_POST['name'] : '';
    $zonekind = isset($_POST['kind']) ? $_POST['kind'] : '';

    if (!is_adminuser() and $allowzoneadd !== true) {
        jtable_respond(null, 'error', "You are not allowed to add zones");
    }
    if (!_valid_label($zonename)) {
        jtable_respond(null, 'error', "Please only use [a-z0-9_/.-]");
    }

    if (!$zonename || !$zonekind) {
        jtable_respond(null, 'error', "Not enough data");
    }

    $zone = new Zone();
    $zone->setkind($zonekind);
    $zone->setname($zonename);

    if ($zonekind != "Slave") {
        if (!isset($_POST['zone']) or isset($_POST['owns'])) {
            foreach ($_POST['nameserver'] as $ns) {
                $zone->addnameserver($ns);
            }
        } else {
            $zone->importdata($_POST['zone']);
        }
        if (isset($defaults['soa_edit_api'])) {
            $zone->setsoaeditapi($defaults['soa_edit_api']);
        }
        if (isset($defaults['soa_edit'])) {
            $zone->setsoaedit($defaults['soa_edit']);
        }
    } else { // Slave
        if (isset($_POST['masters'])) {
            foreach (preg_split('/[,;\s]+/', $_POST['masters'], null, PREG_SPLIT_NO_EMPTY) as $master) {
                $zone->addmaster($master);
            }
        }
    }

    // only admin user and original owner can "recreate" zones that are already
    // present in our own db but got lost in pdns.
    if (!is_adminuser() && get_sess_user() !== get_zone_owner($zonename, get_sess_user())) {
        jtable_respond(null, 'error', 'Zone already owned by someone else');
    }

    $zone = $api->savezone($zone->export());
    $zonename = $zone->name;

    if (is_adminuser() && isset($_POST['owner'])) {
        add_db_zone($zonename, $_POST['owner']);
    } else {
        add_db_zone($zonename, get_sess_user());
    }

    $rrset = $zone->getrrset($old_record['name'], $old_record['type']);
    $rrset->deleteRecord($old_record['content']);
    $zone->addrecord($_POST['name'], $_POST['type'], $_POST['content'], $_POST['disabled'], $_POST['ttl']);

    $api->savezone($zone->export());

    $record['id'] = json_encode($record);
    jtable_respond($zone->getrecord($_POST['name'], $_POST['type'], $_POST['content']), 'single');
    break;

    if (isset($_POST['template']) && $_POST['template'] != 'None') {
        foreach (user_template_list() as $template) {
            if ($template['name'] !== $_POST['template']) continue;

            foreach ($template['records'] as $record) {
                $rrset = $zone->getrrset($record['label'], $record['type']);
                if ($rrset) {
                    $rrset->delete();
                }
            }
            $zone = $api->savezone($zone->export());

            foreach ($template['records'] as $record) {
                $zone->addrecord($record['name'], $record['type'], $record['content']);
            }

            $zone = $api->savezone($zone->export());
            break;
        }
    }

    jtable_respond($zone, 'single');
    break;

case "update":
    $zone = new Zone();
    $zone->parse($api->loadzone($_POST['id']));
    $zoneowner = isset($_POST['owner']) ? $_POST['owner'] : $zone->account;

    if ($zone->account !== $zoneowner) {
        if (!is_adminuser()) {
            header("Status: 403 Access denied");
            jtable_respond(null, 'error', "Can't change owner");
        } else {
            add_db_zone($zone->id, $zoneowner);
            $zone->setaccount($zoneowner);
        }
    }

    $update = false;

    if (isset($_POST['masters'])) {
        $zone->erasemasters();
        foreach(preg_split('/[,;\s]+/', $_POST['masters'], null, PREG_SPLIT_NO_EMPTY) as $master) {
            $zone->addmaster($master);
        }
    }

    jtable_respond($api->savezone($zone->export()), 'single');
    break;

case "createrecord":
    $zone = new Zone();
    $zone->parse($api->loadzone($_GET['zoneid']));
    $record = $zone->addrecord($_POST['name'], $_POST['type'], $_POST['content'], $_POST['disabled'], $_POST['ttl']);
    $api->savezone($zone->export());

    jtable_respond($record, 'single');
    break;

case "editrecord":
    $zone = new Zone();
    $zone->parse($api->loadzone($_GET['zoneid']));

    $old_record = decode_record_id(isset($_POST['id']) ? $_POST['id'] : '');

    $rrset = $zone->getrrset($old_record['name'], $old_record['type']);
    $rrset->deleteRecord($old_record['content']);
    $zone->addrecord($_POST['name'], $_POST['type'], $_POST['content'], $_POST['disabled'], $_POST['ttl']);

    $api->savezone($zone->export());

    $record['id'] = json_encode($record);
    jtable_respond($zone->getrecord($_POST['name'], $_POST['type'], $_POST['content']), 'single');
    break;

case "deleterecord":
    $zone = new Zone();
    $zone->parse($api->loadzone($_GET['zoneid']));

    $old_record = decode_record_id(isset($_POST['id']) ? $_POST['id'] : '');
    $rrset = $zone->getrrset($old_record['name'], $old_record['type']);
    $rrset->deleteRecord($old_record['content']);

    $api->savezone($zone->export());

    jtable_respond(null, 'delete');
    break;

case "export":
    jtable_respond($api->exportzone($_GET['zoneid']), 'single');
    break;

case "gettemplatenameservers":
    $ret = array();
    $type = $_GET['prisec'];

    foreach (user_template_list() as $template) {
        if ($template['name'] !== $_GET['template']) continue;
        $rc = 0;
        foreach ($template['records'] as $record) {
            if ($record['type'] == "NS") {
                if (($type == 'pri' && $rc == 0) or ($type == 'sec' && $rc == 1)) {
                    echo $record['content'];
                    exit(0);
                }
                $rc++;
            }
        }
        echo "";
    }
    break;
case "getformnameservers":
    $inputs = array();
    foreach (user_template_list() as $template) {
        if ($template['name'] !== $_GET['template']) continue;
        foreach ($template['records'] as $record) {
            if ($record['type'] == "NS" and array_search($record['content'], $inputs) === false) {
		array_push($inputs, $record['content']);
                echo '<input type="text" name="nameserver[]" value="'.$record['content'].'" readonly /><br />';
            }
        }
    }
    break;
default:
    jtable_respond(null, 'error', 'No such action');
    break;
}
} catch (Exception $e) {
    jtable_respond(null, 'error', $e->getMessage());
}