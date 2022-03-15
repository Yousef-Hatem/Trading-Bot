<?php 

include 'php/master.php';

welcome();

$dailyReport = false;
$changes  = ['buy' => null, 'sell' => []];
$prices = [];

function trade()
{
    global $dailyReport, $changes, $prices, $max_grids;
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
        
        if (date('H') == 23 && date('i') > 55 && !$dailyReport) {
            $telegram->sendMsg($telegram->dailyReport(date('Y-m-d')));
            $dailyReport = true;
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
            echo "\n\nEnd (Time: ".$time.'s)';
            $nextTime = microtime(true);
        }

        $active = !checkForStopFlag();
    }
}

start();