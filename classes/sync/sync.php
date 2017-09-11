<?php
/**
 * Created by PhpStorm.
 * User: patrick
 * Date: 17/09/10
 * Time: 17:14
 */

namespace logstore_xapi\sync;

use \stdClass as php_obj;

class sync extends php_obj
{
    /**
     * Constructs a new sync.
     *
     */
    public function __construct() {
    }

    public function getLastestLRSEvent($sync_endpoint,$token)
    {
        $token = 'Authorization: Bearer '.$token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $sync_endpoint);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json', $token));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        debugging('Result From LRS: ' . $output, DEBUG_DEVELOPER);
        return json_decode($output);
    }

    public function getLRSToken($apiKey,$apiSecret,$auth_endpoint)
    {
        $data = array('username'=>$apiKey,'password'=>$apiSecret);
        $data_json = json_encode($data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $auth_endpoint);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'X-Requested-With: XMLHttpRequest'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,$data_json);
        $output = curl_exec($ch);
        curl_close($ch);
        debugging('Result From LRS: ' . $output, DEBUG_DEVELOPER);
        return json_decode($output);
    }

}

