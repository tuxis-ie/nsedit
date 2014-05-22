<?php

$apiuser = '';      # The PowerDNS API username
$apipass = '';      # The PowerDNS API-user password
$apiip   = '';      # The IP of the PowerDNS API
$apiport = '8081';  # The port of the PowerDNS API
$apisid  = '';      # PowerDNS's :server_id

$authdb  = "../etc/pdns.users.sqlite3";

$templates = array();
/*
$templates[] = array(
    'name' => 'Tuxis',
    'owner' => 'username', # Set to 'public' to make it available to all users
    'records' => array(
        array(
            'label'     => '',
            'type'      => 'MX',
            'content'   => 'mx2.tuxis.nl',
            'priority'  => '200')
    )
);
*/

$defaults['defaulttype'] = 'Master';                    # Choose between 'Native' or 'Master'
$defaults['primaryns']   = 'unconfigured.primaryns';    # The value of the first NS-record
$defaults['secondaryns'] = 'unconfigured.secondaryns';  # The value of the second NS-record
$defaults['ttl']         = 3600;                        # Default TTL for records
$defaults['priority']    = 0;                           # Default for priority in records




/* No need to change stuf below */
$defaults['defaulttype'] = ucfirst(strtolower($defaults['defaulttype']));

if (!file_exists($authdb)) {
    is_dir(dirname($authdb)) || mkdir(dirname($authdb));
    $db = new SQLite3($authdb, SQLITE3_OPEN_CREATE|SQLITE3_OPEN_READWRITE);
    $createsql = file_get_contents('includes/scheme.sql');
    $db->exec($createsql);
    $salt = bin2hex(openssl_random_pseudo_bytes(16));
    $db->exec("INSERT INTO users (emailaddress, password, isadmin) VALUES ('admin', '".crypt("admin", '$6$'.$salt)."', 1)");
}

?>
