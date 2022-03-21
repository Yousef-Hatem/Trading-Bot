<?php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: AT-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Access-Control-Allow-Origin");

include_once '../../config.php';
include_once '../../php/api/telegram.php';
include_once '../../php/api/functions.php';
include_once '../../php/api/server.php';
include_once '../../php/database/main.php';

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

    return [
        'status' => true,
        'time' => time(),
        'data' => $database->report()
    ];
}

echo json_encode(report());