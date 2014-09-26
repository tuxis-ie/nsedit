<?php

include_once('config.inc.php');
include_once('misc.inc.php');

/* This class is written by Wefact. See https://www.wefact.nl/wefact-hosting/apiv2/
*/

class WeFactAPI {
    
    private $url;
    private $responseType;
    private $apiKey;
    
    function __construct(){
        global $wefactapiurl;
        global $wefactapikey;
        $this->url      = $wefactapiurl;
        $this->api_key  = $wefactapikey;
    }
    
    public function sendRequest($controller, $action, $params){
        
        if(is_array($params)){
            $params['api_key']      = $this->api_key; 
            $params['controller']   = $controller;
            $params['action']       = $action;
        }
        
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,'10');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        $curlResp = curl_exec($ch);
        $curlError = curl_error($ch);
        
        if ($curlError != ''){
            $result = array(
                'controller' => 'invalid',
                'action' => 'invalid',
                'status' => 'error',
                'date' => date('c'),
                'errors' => array($curlError)
            );
        }else{
            $result = json_decode($curlResp, true);
        }
        
        return $result;
    }
}


function do_wefact_auth($u, $p) {
    $wefact = new WeFactApi();
    $r = $wefact->sendRequest('debtor', 'show', array(
        'DebtorCode' => $u));

    if (isset($r['status']) && $r['status'] == 'success') {
        $r = $wefact->sendRequest('debtor', 'checklogin', array(
            'Username'  => $u,
            'Password'  => $p
        ));

        if (isset($r['status']) && $r['status'] == 'success') {
            if (get_user_info($u) == FALSE) {
                add_user($u);
            }
            return TRUE;
        }

        return FALSE;
    } else {
        return -1;
    }
}

?>
