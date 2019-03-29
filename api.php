<?php
/**
 * Created by PhpStorm.
 * User: alexy
 * Date: 2019-03-20
 * Time: 10:59
 */

require_once 'ApiMethods.php';

$apiMethods = new ApiMethods();
$jsonResult = $apiMethods->getUndefinedError();

if(isset($_REQUEST['action'])) {
    switch($_REQUEST['action']) {
        case 'login':
            if(isset($_REQUEST['username']) && isset($_REQUEST['password'])) {
                $jsonResult = $apiMethods->login($_REQUEST['username'], $_REQUEST['password']);
            }

        case 'synchronize':
            if(isset($_REQUEST['memberId']) && isset($_REQUEST['expenses'])) {
                $jsonResult = $apiMethods->synchronize($_REQUEST['memberId'], $_REQUEST['expenses']);
            }
    }
}

echo json_encode($jsonResult);