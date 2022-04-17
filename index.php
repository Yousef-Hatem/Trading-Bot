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

        $trading = $services->isTrading();
        
        $changes['sell'] = $binance->isSellCoin($trading, $changes['sell']);
            
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