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

    public function getLastestLRSEvent()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->get_config('sync_endpoint', 'http://10.236.173.83:9966/api/event/sync/latest'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, array('Accept: application/json', 'Authorization: Bearer ' . 'eyJhbGciOiJIUzUxMiJ9.eyJzdWIiOiI5ZGVmZGNmYy1lZGFhLTQzNzYtOTI2Ny00NDRmNTExMDUyZGEiLCJzY29wZXMiOlsiUk9MRV9PUkdfQURNSU4iXSwidGVuYW50IjoiNTk2MWMzOTRjOGIwMDMwZGRhNjZlMzM4IiwiaXNzIjoiaHR0cDovL2V4YW1wbGUuY29tIiwiaWF0IjoxNTA1MDk0MTYzLCJleHAiOjE1MDUxMDg1NjN9.PJoGBzt4uhuKC1qLu5KYZRSA4thdEWykZE5q_by0miA4cIUFpMcn8fQ3y6Oz3TKNdIYuJxGfHdhfraOEJaaMuQ'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec($ch);
        curl_close($ch);
        debugging('Result From LRS: ' . $output);
    }

}

