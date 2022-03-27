<?php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: AT-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Access-Control-Allow-Origin");

include_once '../../config.php';
include_once '../../php/functions.php';
include_once '../../php/database/main.php';
include_once '../../php/api/config.php';
include_once '../../php/api/functions.php';
include_once '../../php/api/server.php';
include_once '../../php/api/telegram.php';
include_once '../../php/api/binance.php';

function report()
{
    $database = new Database();
    $headers = apache_request_headers();

    if ($_SERVER['REQUEST_METHOD'] != "GET") {
        return [
            'status' => false,
            'error' => "Method Error"
        ];
    }

    if (!isset($headers['AT-KEY'])) {
        return [
            'status' => false,
            'error' => "AT-KEY has not been sent"
        ];
    } elseif ($headers['AT-KEY'] != "OWQrOWtkS1ltK3grYTFNV2VZSTRzZz09") {
        return [
            'status' => false,
            'error' => "AT-KEY Error"
        ];
    }

    if (!isset($_GET['type'])) {
        return [
            'status' => false,
            'error' => "type has not been sent"
        ];
    } elseif ($_GET['type'] != "sell" && $_GET['type'] != "buy") {
        return [
            'status' => false,
            'error' => "This type is not defined. You can send 'sell' or 'buy'"
        ];
    }

    return [
        'status' => true,
        'time' => time(),
        'data' => $database->report($_GET['type'])
    ];
}

echo json_encode(report());