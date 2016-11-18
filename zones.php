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


$quoteus = array('TXT', 'SPF');

/* This function is taken from:
http://pageconfig.com/post/how-to-validate-ascii-text-in-php and got fixed by
#powerdns */
function is_ascii($string) {
    return ( bool ) ! preg_match( '/[\\x00-\\x08\\x0b\\x0c\\x0e-\\x1f\\x80-\\xff]/' , $string );
}

function _valid_label($name) {
    return is_ascii($name) && ( bool ) preg_match("/^([-.a-z0-9_\/\*]+)?.$/i", $name );
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

function record_compare_default($a, $b) {
    if ($cmp = compareName($a['name'], $b['name'])) return $cmp;
    if ($cmp = rrtype_compare($a['type'], $b['type'])) return $cmp;
    if ($cmp = strnatcasecmp($a['content'], $b['content'])) return $cmp;
    return 0;
}

function record_compare_name($a, $b) {
    return record_compare_default($a, $b);
}

function record_compare_type($a, $b) {
    if ($cmp = rrtype_compare($a['type'], $b['type'])) return $cmp;
    if ($cmp = compareName($a['name'], $b['name'])) return $cmp;
    if ($cmp = strnatcasecmp($a['content'], $b['content'])) return $cmp;
    return 0;
}

function record_compare_content($a, $b) {
    if ($cmp = strnatcasecmp($a['content'], $b['content'])) return $cmp;
    if ($cmp = compareName($a['name'], $b['name'])) return $cmp;
    if ($cmp = rrtype_compare($a['type'], $b['type'])) return $cmp;
    return 0;
}

function add_db_zone($zonename, $accountname) {
    if (valid_user($accountname) === false) {
        jtable_respond(null, 'error', "$accountname is not a valid username");
    }
    if (!_valid_label($zonename)) {
        jtable_respond(null, 'error', "$zonename is not a valid zonename");
    }

    if (is_apiuser() && !user_exists($accountname)) {
        add_user($accountname);
    }

    $db = get_db();
    $q = $db->prepare("INSERT OR REPLACE INTO zones (zone, owner) VALUES (?, (SELECT id FROM users WHERE emailaddress = ?))");
    $q->bindValue(1, $zonename, SQLITE3_TEXT);
    $q->bindValue(2, $accountname, SQLITE3_TEXT);
    $q->execute();
}

function delete_db_zone($zonename) {
    if (!_valid_label($zonename)) {
        jtable_respond(null, 'error', "$zonename is not a valid zonename");
    }
    $db = get_db();
    $q = $db->prepare("DELETE FROM zones WHERE zone = ?");
    $q->bindValue(1, $zonename, SQLITE3_TEXT);
    $q->execute();
}

function get_zone_account($zonename, $default) {
    if (!_valid_label($zonename)) {
        jtable_respond(null, 'error', "$zonename is not a valid zonename");
    }
    $db = get_db();
    $q = $db->prepare("SELECT u.emailaddress FROM users u, zones z WHERE z.owner = u.id AND z.zone = ?");
    $q->bindValue(1, $zonename, SQLITE3_TEXT);
    $result = $q->execute();
    $zoneinfo = $result->fetchArray(SQLITE3_ASSOC);
    if (isset($zoneinfo['emailaddress']) && $zoneinfo['emailaddress'] != null ) {
        return $zoneinfo['emailaddress'];
    }

    return $default;
}

function quote_content($content) {
    # empty TXT records are ok, otherwise require surrounding quotes: "..."
    if (strlen($content) == 1 || substr($content, 0, 1) !== '"' || substr($content, -1) !== '"') {
        # fix quoting: first escape all \, then all ", then surround with quotes.
        $content = '"'.str_replace('"', '\\"', str_replace('\\', '\\\\', $content)).'"';
    }

    return $content;
}

function check_account($zone) {
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
        if ($zone->account == '') {
            $zone->setAccount(get_zone_account($zone->name, 'admin'));
        }

        if (!check_account($zone))
            continue;

        if ($action == "listslaves" and $zone->kind == "Slave") {
            array_push($return, $zone->export());
        } elseif ($action == "list" and $zone->kind != "Slave") {
            if ($zone->dnssec) {
                $zone->setKeyinfo($api->getzonekeys($zone->id));
            }
            array_push($return, $zone->export());
        }
    }
    usort($return, "zone_compare");
    jtable_respond($return);
    break;

case "listrecords":
    $zonedata = $api->loadzone($_GET['zoneid']);
    $zone = new Zone();
    $zone->parse($zonedata);
    $records = $zone->rrsets2records();

    if(!empty($_POST['label'])) {
        $records=array_filter($records,
            function ($val) {
                return(stripos($val['name'], $_POST['label']) !== FALSE);
            }
        );
    }

    if(!empty($_POST['type'])) {
        $records=array_filter($records,
            function ($val) {
                return($val['type'] == $_POST['type']);
            }
        );
    }

    if(!empty($_POST['content'])) {
        $records=array_filter($records,
            function ($val) {
                return(stripos($val['content'], $_POST['content']) !== FALSE);
            }
        );
    }

    if (isset($_GET['jtSorting'])) {
        list($scolumn, $sorder) = preg_split("/ /", $_GET['jtSorting']);
        switch ($scolumn) {
            case "type":
                usort($records, "record_compare_type");
                break;
            case "content":
                usort($records, "record_compare_content");
                break;
            default:
                usort($records, "record_compare_name");
                break;
        }
        if ($sorder == "DESC") {
            $records = array_reverse($records);
        }
    } else {
        usort($records, "record_compare_name");
    }
    jtable_respond($records);
    break;

case "delete":
    $zone = $api->loadzone($_POST['id']);
    $api->deletezone($_POST['id']);

    delete_db_zone($zone['name']);
    writelog("Deleted zone ".$zone['name']);
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
    $zone->setKind($zonekind);
    $zone->setName($zonename);

    if ($zonekind != "Slave") {
        if (!isset($_POST['zone']) or isset($_POST['owns'])) {
            foreach ($_POST['nameserver'] as $ns) {
                $zone->addNameserver($ns);
            }
        } else {
            $zone->importData($_POST['zone']);
        }
        if (isset($defaults['soa_edit_api'])) {
            $zone->setSoaEditApi($defaults['soa_edit_api'], True);
        }
        if (isset($defaults['soa_edit'])) {
            $zone->setSoaEdit($defaults['soa_edit']);
        }
    } else { // Slave
        if (isset($_POST['masters'])) {
            foreach (preg_split('/[,;\s]+/', $_POST['masters'], null, PREG_SPLIT_NO_EMPTY) as $master) {
                $zone->addMaster($master);
            }
        }
    }

    // only admin user and original account can "recreate" zones that are already
    // present in our own db but got lost in pdns.
    if (!is_adminuser() && get_sess_user() !== get_zone_account($zonename, get_sess_user())) {
        jtable_respond(null, 'error', 'Zone already owned by someone else');
    }

    $api->savezone($zone->export());

    $zone = new Zone();
    $zone->parse($api->loadzone($zonename));
    $zonename = $zone->name;

    if (is_adminuser() && isset($_POST['account'])) {
        add_db_zone($zonename, $_POST['account']);
        $zone->setAccount($_POST['account']);
    } else {
        add_db_zone($zonename, get_sess_user());
        $zone->setAccount(get_sess_user());
    }

    if (isset($_POST['template']) && $_POST['template'] != 'None') {
        foreach (user_template_list() as $template) {
            if ($template['name'] !== $_POST['template']) continue;

            foreach ($template['records'] as $record) {
                $rrset = $zone->getRRSet($record['label'], $record['type']);
                if ($rrset) {
                    $rrset->delete();
                }
            }
            $api->savezone($zone->export());

            foreach ($template['records'] as $record) {
                $name = $record['name'] != '' ? join(Array($record['name'],'.',$zonename)) : $zonename;
                $zone->addRecord($name, $record['type'], $record['content']);
            }

            break;
        }
    }

    $zone = $api->savezone($zone->export());
    writelog("Created zone ".$zone['name']);
    jtable_respond($zone, 'single');
    break;

case "update":
    $zone = new Zone();
    $zone->parse($api->loadzone($_POST['id']));
    if ($zone->setSoaEditApi($defaults['soa_edit_api']) != False)
        writelog("Set SOA-EDIT-API to ".$defaults['soa_edit_api']." for ",$zone->name);
    $zoneaccount = isset($_POST['account']) ? $_POST['account'] : $zone->account;

    if ($zone->account !== $zoneaccount) {
        if (!is_adminuser()) {
            header("Status: 403 Access denied");
            jtable_respond(null, 'error', "Can't change account");
        } else {
            add_db_zone($zone->name, $zoneaccount);
            $zone->setAccount($zoneaccount);
        }
    }

    if (isset($_POST['masters'])) {
        $zone->eraseMasters();
        foreach(preg_split('/[,;\s]+/', $_POST['masters'], null, PREG_SPLIT_NO_EMPTY) as $master) {
            $zone->addMaster($master);
        }
    }

    writelog("Updated zone ".$zone->name);
    jtable_respond($api->savezone($zone->export()), 'single');
    break;

case "createrecord":
    $zone = new Zone();
    $zone->parse($api->loadzone($_GET['zoneid']));
    if ($zone->setSoaEditApi($defaults['soa_edit_api']) != False)
        writelog("Set SOA-EDIT-API to ".$defaults['soa_edit_api']." for ",$zone->name);

    $name = isset($_POST['name']) ? $_POST['name'] : '';
    $type = $_POST['type'];
    $content = $_POST['content'];

    if ('' == $name) {
        $name = $zone->name;
    } elseif (string_ends_with($name, '.')) {
        # "absolute" name, shouldn't append zone[name] - but check.
        if (!string_ends_with($name, $zone->name)) {
            jtable_respond(null, 'error', "Name $name not in zone ".$zone->name);
        }
    } else if (!string_ends_with($name.'.', $zone->name)) {
        $name = $name . '.' . $zone->name;
    }

    if (!_valid_label($name)) {
        jtable_respond(null, 'error', "Please only use [a-z0-9_/.-]");
    }
    if (!$type) {
        jtable_respond(null, 'error', "Require a type");
    }
    if (!is_ascii($content)) {
        jtable_respond(null, 'error', "Please only use ASCII-characters in your fields");
    }

    if (array_search($type, $quoteus) !== FALSE) {
        $content = quote_content($content);
    }

    $record = $zone->addRecord($name, $type, $content, $_POST['disabled'], $_POST['ttl'], $_POST['setptr']);
    $api->savezone($zone->export());

    writelog("Created record: ".$record['id']);
    jtable_respond($record, 'single');
    break;

case "editrecord":
    $zone = new Zone();
    $zone->parse($api->loadzone($_GET['zoneid']));
    if ($zone->setSoaEditApi($defaults['soa_edit_api']) != False)
        writelog("Set SOA-EDIT-API to ".$defaults['soa_edit_api']." for ",$zone->name);

    $old_record = decode_record_id(isset($_POST['id']) ? $_POST['id'] : '');

    $rrset = $zone->getRRSet($old_record['name'], $old_record['type']);
    $rrset->deleteRecord($old_record['content']);

    $content = $_POST['content'];
    if (array_search($type, $quoteus) !== FALSE) {
        $content = quote_content($content);
    }

    $zone->addRecord($_POST['name'], $_POST['type'], $content, $_POST['disabled'], $_POST['ttl'], $_POST['setptr']);

    $api->savezone($zone->export());

    $record = $zone->getRecord($_POST['name'], $_POST['type'], $content);
    writelog("Updated record ".$_POST['id']." to ".$record['id']);
    jtable_respond($record, 'single');
    break;

case "deleterecord":
    $zone = new Zone();
    $zone->parse($api->loadzone($_GET['zoneid']));
    if ($zone->setSoaEditApi($defaults['soa_edit_api']) != False)
        writelog("Set SOA-EDIT-API to ".$defaults['soa_edit_api']." for ",$zone->name);

    $old_record = decode_record_id(isset($_POST['id']) ? $_POST['id'] : '');
    $rrset = $zone->getRRSet($old_record['name'], $old_record['type']);
    $rrset->deleteRecord($old_record['content']);

    $api->savezone($zone->export());

    writelog("Deleted record ".$_POST['id']);
    jtable_respond(null, 'delete');
    break;

case "export":
    writelog("Exported zone ".$_GET['zoneid']);
    jtable_respond($api->exportzone($_GET['zoneid']), 'single');
    break;

case "clone":
    $name = $_POST['destname'];
    $src  = $_POST['sourcename'];

    if (!string_ends_with($name, '.')) {
        $name = $name.".";
    }

    if (!_valid_label($name)) {
        jtable_respond(null, 'error', "Invalid destination zonename");
    }

    $srczone = new Zone();
    $srczone->parse($api->loadzone($src));
    if ($srczone->setSoaEditApi($defaults['soa_edit_api']) != False)
        writelog("Set SOA-EDIT-API to ".$defaults['soa_edit_api']." for ",$srczone->name);

    $srczone->setId('');
    $srczone->setName($name);
    $srczone->setSerial('');
    $zone = $api->savezone($srczone->export());

    $srczone->parse($zone);

    foreach ($srczone->rrsets as $rrset) {
        $newname = $rrset->name;
        $newname = preg_replace('/'.$src.'$/', $name, $newname);
        $rrset->setName($newname);
    }
    $zone = $api->savezone($srczone->export());

    writelog("Cloned zone $src into $name");
    jtable_respond($zone, 'single');
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
case "formzonelist":
    $zones = $api->listzones();
    $ret = array();
    foreach ($zones as $zone) {
        if ($zone['kind'] == 'Slave')
            continue;
        array_push($ret, array(
            'DisplayText' => $zone['name'],
            'Value'       => $zone['id']));
    }
    jtable_respond($ret, 'options');
    break;

default:
    jtable_respond(null, 'error', 'No such action');
    break;
}
} catch (Exception $e) {
    jtable_respond(null, 'error', $e->getMessage());
}
