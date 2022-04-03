<?php

    class binanceAPI {

        public function request($route, $timestamp = false, $API_SECRET = null, $API_KEY = null, $parameters = [], $method = 'GET')
        {
            global $proxys;
            $proxy = null;

            for ($i=0; $i < count($proxys); $i++) {
                if ((time() - $proxys[$i]['time']) > 60) {
                    $proxys[$i]['time'] = time();
                    $proxys[$i]['numberOrders'] = 0;
                }

                if ($proxys[$i]['numberOrders'] < 1000) {
                    if (!isset($proxy)) {
                        $proxy = $proxys[$i]['proxy'];
                        $proxys[$i]['numberOrders']++;
                    }
                }
            }
            
            $data = "";
            $API = new API();
            $telegram = new telegramAPI();
            $timestamp? $parameters['timestamp'] = $this->timestamp(): '';
            $headers = [sprintf("Content-Type: %s", "application/json")];

            if (isset($API_KEY)) {
                array_push($headers, "X-MBX-APIKEY: ". $API_KEY);
            }

            foreach ($parameters as $key => $value) {
                if ($data) {
                    $data .= '&'.$key.'='.$value;
                } else {
                    $data = $key.'='.$value;
                }
            }
            
            isset($API_SECRET)? $route = $route.'?'.$data."&signature=".$this->signature($data, $API_SECRET): $route = $route.'?'.$data;
            $url = "https://api.binance.com" . $route;

            switch ($method) {
                case 'GET':
                    $data = $API->curlGet($url, $headers, $proxy);
                    break;
                case 'POST':
                    $data = $API->curlPost($url, $headers, [], $proxy);
                    break;
                case 'DELETE':
                    $data = $API->curlDelete($url, $headers, $proxy);
                    break;
                
                default:
                    $data = "Method";
                    break;
            }

            if (is_string($data)) {
                $telegram->sendError($data, $url);
                printCmd("\n $data \n URL: {$route} \n", "Error");
                return false;
            } elseif (isset($data->code)) {
                if ($data->code != 200) {
                    if ($data->code != -2014 && $data->code != -1022) {
                        if ($data->code == -1021) {
                            $this->request($route, $timestamp, $API_SECRET, $API_KEY, $parameters, $method);
                        } else {
                            $telegram->sendError($data->code.': '.$data->msg, $url);
                        }
                    }
                    printCmd("({$data->code}) {$data->msg} \n URL: {$route}", "Error");
                    return false;
                }
            }

            return $data;
        }

        
        public function signature($what, $API_SECRET)
        {
            return hash_hmac("sha256", $what, $API_SECRET);
        }


        public function timestamp()
        {
            $API = new API();

            $request = $API->curlGet("https://api.binance.com/api/v3/time");

            if (isset($request->serverTime)) {
                return $request->serverTime;
            } else {
                return $this->timestamp();
            }
        }


        public function getTickers($symbol = null)
        {
            $parameter = [];

            if (isset($symbol)) {
                $parameter = ['symbol' => $symbol];
            }

            return $this->request("/api/v3/ticker/24hr", false, null, null, $parameter);
        }

        public function getTickersPrice($symbol = null)
        {
            $parameter = [];

            if (isset($symbol)) {
                $parameter = ['symbol' => $symbol];
            }

            $tickersPrice = $this->request("/api/v3/ticker/price", false, null, null, $parameter);

            if (is_array($tickersPrice)) {
                if (count($tickersPrice)) {
                    return $tickersPrice;
                }
            }

            if (isset($symbol) && isset($tickersPrice->symbol)) {
                return $tickersPrice;
            }

            return $this->getTickersPrice($symbol);
        }


        public function exchangeInfo($symbols)
        {
            if (is_array($symbols)) {
                $symbols = implode('","', $symbols);
            }

            $request = $this->request("/api/v3/exchangeInfo", false, null, null, [
                "symbols" => "[\"{$symbols}\"]"
            ]);

            if ($request->serverTime) {
                return $request;
            } else {
                return $this->exchangeInfo($symbols);
            }
        }

        
        public function getCurrenciePrice()
        {
            $prices = [];

            foreach ($this->getTickersPrice() as $tickerPrice) {
                $prices[$tickerPrice->symbol] = $tickerPrice->price;
            }

            return $prices;
        }


        public function tradeFees($user, $symbol)
        {
            if (is_string($symbol)) {
                $symbol = ['symbol' => $symbol];
            } else {
                $symbol = [];
            }

            $request = $this->request("/sapi/v1/asset/tradeFee", true, $user->secret_key, $user->api_key, $symbol);

            if ($request[0]) {
                return $request;
            } else {
                return $this->tradeFees($user, $symbol);
            }
        }


        public function symbolSizeFormat($currency, $size, $filters = null)
        {
            if (!isset($filters)) {
                $filters = $this->exchangeInfo($currency)->symbols[0]->filters;
            }
                        
            $stepSize = (float)$filters[2]->stepSize;
            $precision = strlen($stepSize) - 2;

            return numberFormatPrecision($size, $precision);
        }


        public function getBalance($user, $total)
        {
            $request = $this->request("/api/v3/account", true, $user->secret_key, $user->api_key);

            foreach ($request->balances as $symbol) {
                if ($symbol->asset == "USDT") {
                    $request->total = $symbol->free;
                }
            }

            if ($total) {
                $services = new Services();
                $trading = $services->isTrading();
                foreach ($trading as $symbol) {
                    foreach (json_decode($symbol->users) as $usersTrading) {                        
                        if ($user->username == $usersTrading->username) {
                            $request->total += ($usersTrading->size * $symbol->price);
                        }
                    }
                }
            }

            return $request->total;
        }


        public function currencyCapital($user)
        {
            $server = new serverAPI();
            $balance = $this->getBalance($user, true);

            return $balance/count($server->getCurrenciesConfig());
        }


        public function userCoins($user)
        {
            $server = new serverAPI();
            $currenciesConfig = $server->getCurrenciesConfig();
            $coins = [];

            for ($i=0; $i < $user->total_coin; $i++) { 
                $coin = null;

                foreach ($currenciesConfig as $currencyConfig) {
                    if (isset($coin)) {
                        $index = array_search($currencyConfig->symbol, $coins, true);

                        if (!($index === 0 || $index > 0)) {
                            if ($currencyConfig->price_highest < $coin->price_highest) {
                                $coin = $currencyConfig;
                            }
                        }
                    } else {
                        $coin = $currencyConfig;
                    }
                }

                array_push($coins, $coin->symbol);
            }

            return $coins;
        }


        public function permissionBuy($user, $symbol)
        {
            $userCoins = $this->userCoins($user);
            $index = array_search($symbol, $userCoins, true);

            if (($index === 0 || $index > 0)) {
                $services = new Services();

                $coins = [];
                $numberDeals = 0;

                foreach ($services->isTrading() as $trading) {
                    foreach (json_decode($trading->users) as $userTrading) {
                        if ($user->username == $userTrading->username) {
                            if ($trading->symbol == $symbol) {
                                $numberDeals++;
                            }
                            $i = array_search($symbol, $coins, true);
                            if (!($i === 0 || $i > 0)) {
                                array_push($coins, $symbol);
                            }
                        }
                    }
                }

                $balance = $this->getBalance($user, true);
                if ($balance >= $user->total_budget) {
                    $maxNumberDeals = 30;
                } else {
                    $maxNumberDeals = numberFormatPrecision(($balance/$user->total_coin)/($user->budget_coin/30), 0);
                }
                
                if ($numberDeals < $maxNumberDeals && $numberDeals > 0) {
                    return true;
                } elseif($numberDeals == $maxNumberDeals) {
                    return false;
                }
    
                if ($user->total_coin > count($coins)) {
                    return true;
                }
            }

            return false;
        }


        public function newOrder($symbol)
        {
            $server = new serverAPI();
            $telegram = new telegramAPI();
            $services = new Services();

            $users = [];
            $symbolPrice = $this->getTickersPrice($symbol)->price;
            $exchangeInfo = $this->exchangeInfo($symbol);
            $body = [
                "symbol" => $symbol,
                "side" => "buy",
                "type" => "market"
            ];

            
            foreach ($server->getUsers() as $user) {
                if ($this->permissionBuy($user, $symbol)) {
                    $funds = $user->budget_coin/30;
                    
                    $size = $this->symbolSizeFormat($symbol, $funds/$symbolPrice, $exchangeInfo->symbols[0]->filters);
                    $body['quantity'] = $size;

                    if (Production) {
                        $this->request('/api/v3/order', true, $user->secret_key, $user->api_key, $body, 'POST');
                    } else {
                        $this->request('/api/v3/order/test', true, $user->secret_key, $user->api_key, $body, 'POST');
                    }

                    array_push($users, [
                        'username' => $user->username,
                        'size' => $size
                    ]);
                }
            }

            if (count($users)) {
                $telegram->sendBuy($symbol, $symbolPrice);
                $msgID = $telegram->symbolPriceUpdate($symbolPrice, false)->result->message_id;
                $services->buyCoin($symbol, $symbolPrice, $msgID, $users);
            }

            return true;
        }


        public function salesOrder($users, $currency)
        {
            $server = new serverAPI();
            $sizes = [];

            foreach ($users as $user) 
            {
                $sizes[$user->username] = $user->size;
            }

            foreach ($server->getUsers() as $user) 
            {
                if (isset($sizes[$user->username])) 
                {
                    $body = [
                        "symbol" => $currency,
                        "side" => "sell",
                        "type" => "market",
                        "quantity" => $sizes[$user->username]
                    ];
        
                    if (Production) {
                        $this->request('/api/v3/order', true, $user->secret_key, $user->api_key, $body, 'POST');
                    } else {
                        $this->request('/api/v3/order/test', true, $user->secret_key, $user->api_key, $body, 'POST');
                    }
                }
            }
        }


        public function perfectSymbols($oldChanges = null, $max)
        {
            $server = new serverAPI();
            $services = new Services();
            $trades = $services->isTrading();
            $currencies = [];
            $coins = [];
            $data = [];
            $number = 0;

            foreach ($server->getCurrenciesConfig() as $currency) {
                $price = $currency->price_highest;

                foreach ($trades as $trade) 
                {
                    if ($trade->symbol == $currency->symbol) {
                        $price = $trade->price;
                    }
                }

                $currencies[$currency->symbol] = [
                    'price' => $price,
                    'buy_down' => $currency->ratio_buy,
                    'buy_up' => $currency->execution_buy
                ];
            }

            foreach ($this->getTickersPrice() as $symbol) {
                if (isset($currencies[$symbol->symbol])) {
                    $oldPrice = $currencies[$symbol->symbol]['price'];

                    $currency = [
                        'symbol' => $symbol->symbol,
                        'change' => number_format((($symbol->price*100)/$oldPrice) - 100, 2)
                    ];
                    
                    array_push($coins, $currency);
                }
            }

            foreach ($coins as $coin) {
                $currency = $currencies[$coin['symbol']];
                if ($coin['change'] <= ($currency['buy_down'] - (2*$currency['buy_down'])) || isset($oldChanges[$coin['symbol']])) {
                    $data[$coin['symbol']] = $coin['change'];
                }
            }

            if (is_array($oldChanges)) {
                foreach ($oldChanges as $key => $oldChange) {
                    if ($max > $number) {
                        $change = $data[$key] - $oldChange;

                        if ($change > 0) {
                            $currency = $currencies[$key];
                            
                            if ($change >= $currency['buy_up']) {
                                printCmd($data[$key]." - ".$oldChange." >= ".$currency['buy_up'], $key);
                                $this->newOrder($key);
                                $number++;

                                unset($data[$key]);
                            } else {    
                                $data[$key] = $oldChange;
                            }
                        }
                    }
                }
            }

            foreach ($data as $key => $change) {
                if ($change > ($currency['buy_down'] - (2*$currency['buy_down']))) {
                    unset($data[$key]);
                }
            }
            
            return $data;
        }


        public function isSellCoin($trading, $oldChanges = [], $oldPrices = [])
        {
            $server = new serverAPI();
            $telegram = new telegramAPI();
            $services = new Services();
            $currenciesConfig = [];
            $changes = [];
            $pricesUpdate = [];
            $prices = [];

            foreach ($server->getCurrenciesConfig() as $currencyConfig) {
                $currenciesConfig[$currencyConfig->symbol] = $currencyConfig;
            }

            foreach ($this->getTickersPrice() as $tickerPrice) {
                $prices[$tickerPrice->symbol] = $tickerPrice->price;
            }

            foreach ($trading as $coin) {
                $currentPrice = $prices[$coin->symbol];
                
                $change = number_format((($currentPrice*100)/$coin->price) - 100, 2);
                $changes[$coin->id] = $change;
                
                $priceUpdate = false;
                
                if (isset($oldPrices[$coin->id])) {
                    if ($currentPrice != $oldPrices[$coin->id]) {
                        $priceUpdate = true;
                    }
                }

                if ($priceUpdate) {
                    $gain = (($currentPrice/$coin->price)*100)-100;
                    $telegram->symbolPriceUpdate($currentPrice, $coin->msg_id, $gain);
                }

                $pricesUpdate[$coin->id] = $currentPrice;

                if (isset($oldChanges[$coin->id])) {
                    $currencyConfig = $currenciesConfig[$coin->symbol];
                    $sell_up = $currencyConfig->ratio_sell;
                    $sell_down = $currencyConfig->execution_sell;

                    if ($oldChanges[$coin->id] >= $sell_up && $change - $oldChanges[$coin->id] < 0) {
                        $changes[$coin->id] = $oldChanges[$coin->id];

                        if ($change - $oldChanges[$coin->id] <= ($sell_down - (2*$sell_down))) {
                            $this->salesOrder(json_decode($coin->users), $coin->symbol);
                            $services->sellCoin($coin->id, $currentPrice);
                            $telegram->deleteMsg($coin->msg_id);
                            $telegram->sendSell($coin->symbol, $currentPrice);
                        }
                    }
                }
            }

            return ['changes' => $changes, 'prices' => $pricesUpdate];
        }
    }