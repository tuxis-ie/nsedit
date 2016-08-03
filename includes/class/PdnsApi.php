<?php

include_once('apihandler.php');

class PdnsAPI {
    public function __construct() {
        $this->http = new ApiHandler();
    }

    public function listzones($q = FALSE) {
        $api = clone $this->http;
        $api->method = 'GET';
        if ($q) {
            $api->url = "/servers/localhost/search-data?q=*".$q."*&max=25";
            $api->call();
            $ret = Array();
            $seen = Array();

            foreach ($api->json as $result) {
                if (isset($seen[$result['zone_id']])) {
                    continue;
                }
                $zone = $this->loadzone($result['zone_id']);
                unset($zone['rrsets']);
                array_push($ret, $zone);
                $seen[$result['zone_id']] = 1;
            }

            return $ret;
        }
        $api->url = "/servers/localhost/zones";
        $api->call();

        return $api->json;
    }

    public function loadzone($zoneid) {
        $api = clone $this->http;
        $api->method = 'GET';
        $api->url = "/servers/localhost/zones/$zoneid";
        $api->call();

        return $api->json;
    }

    public function savezone($zone) {
        $api = clone $this->http;
        // We have to split up RRSets and Zoneinfo.
        // First, update the zone
        $zonedata = $zone;
        unset($zonedata['id']);
        unset($zonedata['url']);
        unset($zonedata['rrsets']);

        if ($zone['serial'] == '') {
            $api->method = 'POST';
            $api->url = '/servers/localhost/zones';
            $api->content = json_encode($zonedata);
            $api->call();

            return $api->json;
        }
        $api->method = 'PUT';
        $api->url = $zone['url'];
        $api->content = json_encode($zonedata);
        $api->call();

        // Then, update the rrsets
        $api->method = 'PATCH';
        $api->content = json_encode(Array('rrsets' => $zone['rrsets']));
        $api->call();
    }

    public function deletezone($zoneid) {
        $api = clone $this->http;
        $api->method = 'DELETE';
        $api->url = "/servers/localhost/zones/$zoneid";
        $api->call();

        return $api->json;
    }

    public function getzonekeys($zoneid) {
        $ret = array();
        $api = clone $this->http;
        $api->method = 'GET';
        $api->url = "/servers/localhost/zones/$zoneid/cryptokeys"

        $api->call();

        foreach ($api->json as $key) {
            if (!isset($key['active']))
                continue;

            $key['dstxt'] = $zoneid . ' IN DNSKEY '.$key['dnskey']."\n\n";

            if (isset($key['ds'])) {
                foreach ($key['ds'] as $ds) {
                    $key['dstxt'] .= $zoneid . ' IN DS '.$ds."\n";
                }
                unset($key['ds']);
            }
            array_push($ret, $key);
        }

        return $ret;
    }

}

?>
