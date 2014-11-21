<?php

include_once('includes/config.inc.php');
include_once('includes/session.inc.php');
include_once('includes/misc.inc.php');

if (!is_csrf_safe()) {
    header('Status: 403');
    header('Location: ./index.php');
    jtable_respond(null, 'error', "Authentication required");
}

function api_request($path, $opts = null, $type = null) {
    global $apisid, $apiuser, $apipass, $apiip, $apiport, $authmethod;

    $url = "http://$apiip:$apiport${path}";

    if ($authmethod == "auto") {
        $ad = curl_init();
        curl_setopt($ad, CURLOPT_HTTPHEADER, array('X-API-Key: '.$apipass));
        curl_setopt($ad, CURLOPT_URL, "http://$apiip:$apiport/servers/localhost/statistics");
        curl_setopt($ad, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ad);
        if (curl_getinfo($ad, CURLINFO_HTTP_CODE) == 401) {
            $authmethod = 'userpass';
        } else {
            $authmethod = 'xapikey';
        }
    }

    $ch = curl_init();
    if ($authmethod == "xapikey") {
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-API-Key: '.$apipass));
    } else {
        curl_setopt($ch, CURLOPT_USERPWD, "$apiuser:$apipass");
    }
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    if ($opts) {
        if (!$type) {
            $type = 'POST';
        }
        $postdata = json_encode($opts);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    }
    switch ($type) {
    case 'DELETE':
    case 'PATCH':
    case 'PUT':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
        break;
    case 'POST':
        break;
    }

    $return = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $json = json_decode($return, 1);

    if (isset($json['error'])) {
        jtable_respond(null, 'error', "API Error $code: ".$json['error']);
    } elseif ($code < 200 || $code >= 300) {
        if ($code == 401) {
            $code = "Authentication failed. Have you configured your authmethod correct?";
        }
        jtable_respond(null, 'error', "API Error: $code");
    }
    return $json;
}

function zones_api_request($opts = null, $type = 'POST') {
    global $apisid;

    return api_request("/servers/${apisid}/zones", $opts, $type);
}

function get_all_zones() {
    return zones_api_request();
}

function _get_zone_by_key($key, $value) {
    if ($value !== '') {
        foreach (get_all_zones() as $zone) {
            if ($zone[$key] === $value) {
                $zone['owner'] = get_zone_owner($zone['name'], 'admin');

                if (!check_owner($zone)) {
                    jtable_respond(null, 'error', 'Access denied');
                }
                return $zone;
            }
        }
    }
    header('Status: 404 Not found');
    jtable_respond(null, 'error', "Zone not found");
}

function get_zone_by_url($zoneurl) {
    return _get_zone_by_key('url', $zoneurl);
}

function get_zone_by_id($zoneid) {
    return _get_zone_by_key('id', $zoneid);
}

function get_zone_by_name($zonename) {
    return _get_zone_by_key('name', $zonename);
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

function make_record($zone, $input) {
    global $defaults;

    $name = isset($input['name']) ? $input['name'] : '';

    if ('' == $name) {
        $name = $zone['name'];
    } elseif (string_ends_with($name, '.')) {
        # "absolute" name, shouldn't append zone[name] - but check.
        $name = substr($name, 0, -1);
        if (!string_ends_with($name, $zone['name'])) {
            jtable_respond(null, 'error', "Name $name not in zone ".$zone['name']);
        }
    } else if (!string_ends_with($name, $zone['name'])) {
        $name = $name . '.' . $zone['name'];
    }

    $ttl = (int) ((isset($input['ttl']) && $input['ttl']) ? $input['ttl'] : $defaults['ttl']);
    $priority = (int) ((isset($input['priority']) && $input['priority']) ? $input['priority'] : $defaults['priority']);
    $type = isset($input['type']) ? $input['type'] : '';
    $disabled = (bool) (isset($input['disabled']) && $input['disabled']);

    $content = isset($input['content']) ? $input['content'] : '';

    if ($type === 'TXT') {
        # empty TXT records are ok, otherwise require surrounding quotes: "..."
        if (strlen($content) == 1 || substr($content, 0, 1) !== '"' || substr($content, -1) !== '"') {
            # fix quoting: first escape all \, then all ", then surround with quotes.
            $content = '"'.str_replace('"', '\\"', str_replace('\\', '\\\\', $content)).'"';
        }
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

    return array(
        'disabled' => $disabled,
        'type' => $type,
        'name' => $name,
        'ttl'  => $ttl,
        'priority'  => $priority,
        'content'   => $content);
}

function update_records($zone, $name_and_type, $inputs) {
    # need one "record" to extract name and type, in case we have no inputs
    # (deletion of all records)
    $name_and_type = make_record($zone, $name_and_type);
    $name = $name_and_type['name'];
    $type = $name_and_type['type'];

    $records = array();
    foreach ($inputs as $input) {
        $record = make_record($zone, $input);
        if ($record['name'] !== $name || $record['type'] !== $type) {
            jtable_respond(null, 'error', "Records not matching");
        }

        array_push($records, $record);
    }

    if (!_valid_label($name)) {
        jtable_respond(null, 'error', "Please only use [a-z0-9_/.-]");
    }

    $patch = array(
        'rrsets' => array(array(
            'name' => $name,
            'type' => $type,
            'changetype' => count($records) ? 'REPLACE' : 'DELETE',
            'records' => $records)));

    api_request($zone['url'], $patch, 'PATCH');
}

function create_record($zone, $input) {
    $record = make_record($zone, $input);
    $records = get_records_by_name_type($zone, $record['name'], $record['type']);
    array_push($records, $record);

    $patch = array(
        'rrsets' => array(array(
            'name' => $record['name'],
            'type' => $record['type'],
            'changetype' => 'REPLACE',
            'records' => $records)));

    api_request($zone['url'], $patch, 'PATCH');

    return $record;
}

function get_records_by_name_type($zone, $name, $type) {
    $zone = api_request($zone['url']);
    $records = array();
    foreach ($zone['records'] as $record) {
        if ($record['name'] == $name and $record['type'] == $type) {
            array_push($records, $record);
        }
    }

    return $records;
}

function decode_record_id($id) {
    $record = json_decode($id, 1);
    if (!$record
        || !isset($record['name'])
        || !isset($record['type'])
        || !isset($record['ttl'])
        || !isset($record['priority'])
        || !isset($record['content'])
        || !isset($record['disabled'])) {
        jtable_respond(null, 'error', "Invalid record id");
    }
    return $record;
}

# get all records with same name and type but different id (content)
# requires records with id to be present
# SOA records match always, regardless of content.
function get_records_except($zone, $exclude) {
    $is_soa = ($exclude['type'] == 'SOA');

    $found = false;
    $zone = api_request($zone['url']);
    $records = array();
    foreach ($zone['records'] as $record) {
        if ($record['name'] == $exclude['name'] and $record['type'] == $exclude['type']) {
            if ($is_soa) {
                # SOA changes all the time (serial); we can't match it in a sane way.
                # OTOH we know it is unique anyway - just pretend we found a match.
                $found = true;
            } elseif ($record['content'] != $exclude['content']
                or $record['ttl']        != $exclude['ttl']
                or $record['priority']   != $exclude['priority']
                or $record['disabled']   != $exclude['disabled']) {
                array_push($records, $record);
            } else {
                $found = true;
            }
        }
    }

    if (!$found) {
        header("Status: 404 Not Found");
        jtable_respond(null, 'error', "Didn't find record with id");
    }

    return $records;
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
    if ($cmp = compareName($a['name'], $b['name'])) return $cmp;
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
    if ($cmp = ($a['priority'] - $b['priority'])) return $cmp;
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

function get_zone_keys($zone) {
    $ret = array();
    foreach (api_request($zone['url'] . "/cryptokeys") as $key) {
        if (!isset($key['active']))
            continue;

        $key['dstxt'] = $zone['name'] . ' IN DNSKEY '.$key['dnskey']."\n\n";

        if (isset($key['ds'])) {
            foreach ($key['ds'] as $ds) {
                $key['dstxt'] .= $zone['name'] . ' IN DS '.$ds."\n";
            }
            unset($key['ds']);
        }
        $ret[] = $key;
    }

    return $ret;
}

function check_owner($zone) {
    return is_adminuser() or ($zone['owner'] === get_sess_user());
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
} else {
    jtable_respond(null, 'error', 'No action given');
}

switch ($action) {

case "list":
case "listslaves":
    $return = array();
    $q = isset($_POST['domsearch']) ? $_POST['domsearch'] : false;
    foreach (get_all_zones() as $zone) {
        $zone['owner'] = get_zone_owner($zone['name'], 'admin');
        if (!check_owner($zone))
            continue;

        if ($q && !preg_match("/$q/", $zone['name'])) {
            continue;
        }

        if ($action == "listslaves" and $zone['kind'] == "Slave") {
            array_push($return, $zone);
        } elseif ($action == "list" and $zone['kind'] != "Slave") {
            if ($zone['dnssec']) {
                $zone['keyinfo'] = get_zone_keys($zone);
            }
            array_push($return, $zone);
        }
    }
    usort($return, "zone_compare");
    jtable_respond($return);
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

    $createOptions = array(
        'name' => $zonename,
        'kind' => $zonekind,
        );

    $nameservers = array();
    if (isset($_POST['nameserver1']) && $_POST['nameserver1'] != null) {
        array_push($nameservers, $_POST['nameserver1']);
    }
    if (isset($_POST['nameserver2']) && $_POST['nameserver2'] != null) {
        array_push($nameservers, $_POST['nameserver2']);
    }

    if ($zonekind != "Slave") {
        $createOptions['nameservers'] = $nameservers;
        if (!isset($_POST['zone'])) {
            if (0 == count($nameservers)) {
                jtable_respond(null, 'error', "Require nameservers");
            }
        } else {
            $createOptions['zone'] = $_POST['zone'];
        }
        if (isset($defaults['soa_edit_api'])) {
            $createOptions['soa_edit_api'] = $defaults['soa_edit_api'];
        }
        if (isset($defaults['soa_edit'])) {
            $createOptions['soa_edit'] = $defaults['soa_edit'];
        }
    } else { // Slave
        if (isset($_POST['masters'])) {
            $createOptions['masters'] = preg_split('/[,;\s]+/', $_POST['masters'], null, PREG_SPLIT_NO_EMPTY);
        }
        if (0 == count($createOptions['masters'])) {
            jtable_respond(null, 'error', "Slave requires master servers");
        }
    }

    // only admin user and original owner can "recreate" zones that are already
    // present in our own db but got lost in pdns.
    if (!is_adminuser() && get_sess_user() !== get_zone_owner($zonename, get_sess_user())) {
        jtable_respond(null, 'error', 'Zone already owned by someone else');
    }

    $zone = zones_api_request($createOptions);
    $zonename = $zone['name'];

    if (is_adminuser() && isset($_POST['owner'])) {
        add_db_zone($zonename, $_POST['owner']);
    } else {
        add_db_zone($zonename, get_sess_user());
    }

    if (isset($_POST['template']) && $_POST['template'] != 'None') {
        foreach (user_template_list() as $template) {
            if ($template['name'] !== $_POST['template']) continue;

            foreach ($template['records'] as $record) {
                if (isset($record['label'])) {
                    $record['name'] = $record['label'];
                    unset($record['label']);
                }
                create_record($zone, $record);
            }
            break;
        }
    }

    if (isset($_POST['zone']) && isset($_POST['owns']) && $_POST['owns'] && count($nameservers)) {
        $records = array();
        foreach ($nameservers as $ns) {
            array_push($records, array('type' => 'NS', 'content' => $ns));
        }
        update_records($zone, $records[0], $records);
    }

    unset($zone['records']);
    unset($zone['comments']);
    jtable_respond($zone, 'single');
    break;

case "update":
    $zone = get_zone_by_id(isset($_POST['id']) ? $_POST['id'] : '');

    $zoneowner = isset($_POST['owner']) ? $_POST['owner'] : $zone['owner'];

    if ($zone['owner'] !== $zoneowner) {
        if (!is_adminuser()) {
            header("Status: 403 Access denied");
            jtable_respond(null, 'error', "Can't change owner");
        } else {
            add_db_zone($zone['name'], $zoneowner);
            $zone['owner'] = $zoneowner;
        }
    }

    $update = false;

    if (isset($_POST['masters'])) {
        $zone['masters'] = preg_split('/[,;\s]+/', $_POST['masters'], null, PREG_SPLIT_NO_EMPTY);
        $update = true;
    }

    if ($update) {
        $zoneUpdate = $zone;
        unset($zoneUpdate['id']);
        unset($zoneUpdate['url']);
        unset($zoneUpdate['owner']);
        $newZone = api_request($zone['url'], $zoneUpdate, 'PUT');
        $newZone['owner'] = $zone['owner'];
    } else {
        $newZone = $zone;
    }
    unset($newZone['records']);
    unset($newZone['comments']);

    jtable_respond($newZone, 'single');
    break;

case "delete":
    $zone = get_zone_by_id(isset($_POST['id']) ? $_POST['id'] : '');

    api_request($zone['url'], array(), 'DELETE');
    delete_db_zone($zone['name']);
    jtable_respond(null, 'delete');
    break;

case "listrecords":
    $zone = get_zone_by_url(isset($_GET['zoneurl']) ? $_GET['zoneurl'] : '');

    $a = api_request($zone['url']);
    $records = $a['records'];
    foreach ($records as &$record) {
        $record['id'] = json_encode($record);
    }
    unset($record);
    usort($records, "record_compare");
    jtable_respond($records);
    break;

case "createrecord":
    $zone = get_zone_by_url(isset($_GET['zoneurl']) ? $_GET['zoneurl'] : '');
    $record = create_record($zone, $_POST);

    $record['id'] = json_encode($record);
    jtable_respond($record, 'single');
    break;

case "editrecord":
    $zone = get_zone_by_url(isset($_GET['zoneurl']) ? $_GET['zoneurl'] : '');
    $old_record = decode_record_id(isset($_POST['id']) ? $_POST['id'] : '');

    $records = get_records_except($zone, $old_record);

    $record = make_record($zone, $_POST);

    if ($record['name'] !== $old_record['name']) {
        # rename:
        $newRecords = get_records_by_name_type($zone, $record['name'], $record['type']);
        array_push($newRecords, $record);
        update_records($zone, $old_record, $records); # remove from old list
        update_records($zone, $record, $newRecords); # add to new list
    } else {
        array_push($records, $record);
        update_records($zone, $record, $records);
    }

    $record['id'] = json_encode($record);
    jtable_respond($record, 'single');
    break;

case "deleterecord":
    $zone = get_zone_by_url(isset($_GET['zoneurl']) ? $_GET['zoneurl'] : '');
    $old_record = decode_record_id(isset($_POST['id']) ? $_POST['id'] : '');

    $records = get_records_except($zone, $old_record);

    update_records($zone, $old_record, $records);
    jtable_respond(null, 'delete');
    break;

default:
    jtable_respond(null, 'error', 'No such action');
    break;
}
