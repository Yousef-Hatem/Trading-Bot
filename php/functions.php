<?php

function welcome()
{
    echo "🤖 Welcome 👋";
    echo "\n🤖 The system is working 👍";
    echo "\n----------------------------";
    echo "\n";
}

function dd($data) 
{    
    echo "<pre style='background-color: #222;color: #fff;padding: 10px;'>";

        print_r($data);
        
    echo "</pre>";
}

function printCmd($data, $name = 'data') 
{
    if ($data !== '' && !is_null($data)) {
        if (!is_string($data) && !is_integer($data) && !is_float($data) && !is_bool($data)) {
            echo "<pre> {$name} ";
        
                print_r($data);
                
            echo "</pre>";
        } else {
            echo "\n {$name}: {$data} \n";
        }
    } else {
        echo "\n << {$name}: Empty >> \n";
    }
}

function numberFormatPrecision($number, $precision = 2, $separator = '.')
{
    $numberParts = explode($separator, $number);
    $response = $numberParts[0];
    if (count($numberParts)>1 && $precision > 0) {
        $response .= $separator;
        $response .= substr($numberParts[1], 0, $precision);
    }
    return $response;
}