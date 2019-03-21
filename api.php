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

if(isset($_GET['action'])) {
    switch($_GET['action']) {
        case 'login':
            if(isset($_GET['username']) && isset($_GET['password'])) {
                $jsonResult = $apiMethods->login($_GET['username'], $_GET['password']);
            }
    }
}

echo json_encode($jsonResult);