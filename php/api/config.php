<?php

$database = new Database();

$settings = $database->getSettings();

$bot_key = $settings['bot_key'];

$chat_id = $settings['chat_id'];

$max_grids = $settings['max_grids'];

$proxys = [
    [
        "proxy" => 'main',
        "numberOrders" => 0,
        "time" => time()
    ]
];

foreach (Proxys as $proxy) {
    array_push($proxys, [
        "proxy" => $proxy,
        "numberOrders" => 0,
        "time" => time()
    ]);
}