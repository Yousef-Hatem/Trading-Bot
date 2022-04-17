<?php 

include 'php/master.php';

welcome();

$changes  = ['buy' => null, 'sell' => []];

function trade()
{
    global $changes, $max_grids;
    $time = time();

    $services = new Services();
    $telegram = new telegramAPI();
    
    if ($services->getStatus()) {
        $binance = new binanceAPI();
        $server = new serverAPI();

        $trading = $services->isTrading();
        $CurrenciesConfig = $server->getCurrenciesConfig();
        $tickersPrice = $binance->getTickersPrice();
        
        $isSellCoinTime = time();
        $changes['sell'] = $binance->isSellCoin($trading, $changes['sell'], $CurrenciesConfig, $tickersPrice);
        
        if ((time() - $isSellCoinTime) >= 10) {
            $CurrenciesConfig = $server->getCurrenciesConfig();
            $tickersPrice = $binance->getTickersPrice();
        }

        $trading = $services->isTrading();

        if ($max_grids > count($trading)) {
            $changes['buy'] = $binance->perfectSymbols($changes['buy'], $max_grids - count($trading), $tickersPrice, $CurrenciesConfig, $trading);
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