<?php

include_once 'includes/config.inc.php';

class ApiHandler
{
    public function __construct()
    {
        global $apiip, $apiport, $apipass, $apiproto, $apisslverify;

        $this->headers = [];
        $this->hostname = $apiip;
        $this->port = $apiport;
        $this->auth = $apipass;
        $this->proto = $apiproto;
        $this->sslverify = $apisslverify;
        $this->curlh = curl_init();
        $this->method = 'GET';
        $this->content = false;
        $this->apiurl = '';
    }

    public function addheader($field, $content)
    {
        $this->headers[$field] = $content;
    }

    private function authheaders()
    {
        $this->addheader('X-API-Key', $this->auth);
    }

    private function apiurl()
    {
        $tmp = new ApiHandler();

        $tmp->url = '/api';
        $tmp->go();

        if ($tmp->json[0]['version'] <= 1) {
            $this->apiurl = $tmp->json[0]['url'];
        } else {
            throw new Exception('Unsupported API version');
        }
    }

    private function curlopts()
    {
        $this->authheaders();
        $this->addheader('Accept', 'application/json');

        if (defined('curl_reset')) {
            curl_reset($this->curlh);
        } else {
            $this->curlh = curl_init();
        }
        curl_setopt($this->curlh, CURLOPT_HTTPHEADER, []);
        curl_setopt($this->curlh, CURLOPT_RETURNTRANSFER, 1);

        if (strcasecmp($this->proto, 'https')) {
            curl_setopt($this->curlh, CURLOPT_SSL_VERIFYPEER, $this->sslverify);
        }

        $setheaders = [];

        foreach ($this->headers as $k => $v) {
            array_push($setheaders, join(': ', [$k, $v]));
        }
        curl_setopt($this->curlh, CURLOPT_HTTPHEADER, $setheaders);
    }

    private function baseurl()
    {
        return $this->proto . '://' . $this->hostname . ':' . $this->port . $this->apiurl;
    }

    private function go()
    {
        $this->curlopts();

        if ($this->content) {
            $this->addheader('Content-Type', 'application/json');
            curl_setopt($this->curlh, CURLOPT_POST, 1);
            curl_setopt($this->curlh, CURLOPT_POSTFIELDS, $this->content);
        }

        switch ($this->method) {
            case 'POST':
                curl_setopt($this->curlh, CURLOPT_POST, 1);
                break;
            case 'GET':
                curl_setopt($this->curlh, CURLOPT_POST, 0);
                break;
            case 'DELETE':
            case 'PATCH':
            case 'PUT':
                curl_setopt($this->curlh, CURLOPT_CUSTOMREQUEST, $this->method);
                break;
        }

        curl_setopt($this->curlh, CURLOPT_URL, $this->baseurl() . $this->url);

        $return = curl_exec($this->curlh);
        $code = curl_getinfo($this->curlh, CURLINFO_HTTP_CODE);
        $json = json_decode($return, 1);

        if (isset($json['error'])) {
            throw new Exception("API Error $code: " . $json['error']);
        } elseif ($code < 200 || $code >= 300) {
            if ($code == 401) {
                throw new Exception('Authentication failed. Have you configured your authmethod correct?');
            }
            throw new Exception("Curl Error: $code " . curl_error($this->curlh));
        }

        $this->json = $json;
    }

    public function call()
    {
        if (substr($this->url, 0, 1) != '/') {
            $this->url = '/' . $this->url;
        }
        $this->apiurl();
        $this->url = str_replace($this->apiurl, '', $this->url);
        $this->go();
    }
}
