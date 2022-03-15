<?php

$database = new Database();

$settings = $database->getSettings();

$bot_key = $settings['bot_key'];

$chat_id = $settings['chat_id'];

$max_grids = $settings['max_grids'];