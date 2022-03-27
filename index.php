<?php 

include 'php/master.php';

welcome();

$changes  = ['buy' => null, 'sell' => []];
$prices = [];

function trade()
{
    global $changes, $prices, $max_grids;
    $time = time();

    $database = new Database();
    $telegram = new telegramAPI();
    
    if ($database->getStatus()) {
        $binance = new binanceAPI();

        $trading = $database->isTrading();
        
        $data = $binance->isSellCoin($trading, $changes['sell'], $prices);
        $changes['sell'] = $data['changes'];
        $prices = $data['prices'];
            
        if ($max_grids > count($trading)) {
            $changes['buy'] = $binance->perfectSymbols($changes['buy'], $max_grids - count($trading));
        }
    }

    $telegram->reply();

    return time() - $time;
}

function checkForStopFlag() {
    return false;
}

function start() {
    $active = true;
    $nextTime = microtime(true);

    while($active) {
        if (microtime(true) >= $nextTime) {
            $time = trade();
            echo "\n\nEnd (Time: ".$time.'s) '.date('d h:i:s');
            $nextTime = microtime(true);
        }

        $active = !checkForStopFlag();
    }
}

start();