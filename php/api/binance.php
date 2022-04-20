<?php

    class binanceAPI {

        public function request($route, $timestamp = false, $user = null, $parameters = [], $method = 'GET')
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

            if (isset($user)) {
                array_push($headers, "X-MBX-APIKEY: ". $user->api_key);
            }

            foreach ($parameters as $key => $value) {
                if ($data) {
                    $data .= '&'.$key.'='.$value;
                } else {
                    $data = $key.'='.$value;
                }
            }
            
            isset($user)? $route = $route.'?'.$data."&signature=".$this->signature($data, $user->secret_key): $route = $route.'?'.$data;
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
                printCmd("\n $data \n URL: {$route} \n", "Error");
                return false;
            } elseif (isset($data->code)) {
                if ($data->code != 200) {
                    if ($data->code != -2014 && $data->code != -1022) {
                        if ($data->code == -1021) {
                            $this->request($route, $timestamp, $user, $parameters, $method);
                        } else {
                            $telegram->sendError($data, $route, $user);
                        }
                    }
                    printCmd("({$data->code}) {$data->msg} \n URL: {$route}", "Error");
                    if ($data->code == -2010) {
                        return $data->code;
                    }
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

            return $this->request("/api/v3/ticker/24hr", false, null, $parameter);
        }

        public function getTickersPrice($symbol = null)
        {
            $parameter = [];

            if (isset($symbol)) {
                $parameter = ['symbol' => $symbol];
            }

            $tickersPrice = $this->request("/api/v3/ticker/price", false, null, $parameter);

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

            $request = $this->request("/api/v3/exchangeInfo", false, null, [
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

            $request = $this->request("/sapi/v1/asset/tradeFee", true, $user, $symbol);

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
            $request = $this->request("/api/v3/account", true, $user);

            foreach ($request->balances as $symbol) {
                if ($symbol->asset == "USDT") {
                    $request->total = $symbol->free;
                }
            }

            if ($total) {
                $services = new Services();
                $trading = $services->isTrading();
                foreach ($trading as $symbol) {
                    if ($user->username == $symbol->username) {
                        $request->total += ($symbol->size * $symbol->price);
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


        public function userCoins($user, $symbolsPrices, $trades, $currenciesConfig)
        {
            $coins = [];

            foreach ($trades as $trade) 
            {
                if (count($coins) < $user->total_coin) {
                    $index = array_search($trade->symbol, $coins, true);

                    if ($trade->username === $user->username && !($index === 0 || $index > 0)) 
                    {
                        array_push($coins, $trade->symbol);
                    }
                }
            }

            for ($i=0; $i < $user->total_coin; $i++) {
                if (count($coins) < $user->total_coin) {                
                    $coin = null;

                    foreach ($currenciesConfig as $currencyConfig) 
                    {
                        if (isset($coin)) {
                            $index = array_search($currencyConfig->symbol, $coins, true);

                            if (!($index === 0 || $index > 0) && $currencyConfig->price_highest > $symbolsPrices[$currencyConfig->symbol]) {
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
            }

            return $coins;
        }


        public function permissionBuy($user, $symbol, $symbolsPrices, $trades, $currenciesConfig)
        {
            $userCoins = $this->userCoins($user, $symbolsPrices, $trades, $currenciesConfig);
            $index = array_search($symbol, $userCoins, true);

            if (($index === 0 || $index > 0)) {
                $coins = [];
                $numberDeals = 0;

                foreach ($trades as $trading) {
                    if ($user->username == $trading->username) {
                        if ($trading->symbol == $symbol) {
                            $numberDeals++;
                        }
                        $i = array_search($symbol, $coins, true);
                        if (!($i === 0 || $i > 0)) {
                            array_push($coins, $symbol);
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
                } elseif($numberDeals >= $maxNumberDeals) {
                    return false;
                }
    
                if ($user->total_coin > count($coins)) {
                    return true;
                }
            }

            return false;
        }


        public function newOrder($symbol, $tickersPrice, $trades, $currenciesConfig)
        {
            $server = new serverAPI();
            $telegram = new telegramAPI();
            $services = new Services();
            $users = [];
            $exchangeInfo = null;
            
            $symbolsPrices = [];
            foreach ($tickersPrice as $tickerPrice) {
                $symbolsPrices[$tickerPrice->symbol] = $tickerPrice->price;
            }

            $body = [
                "symbol" => $symbol,
                "side" => "buy",
                "type" => "market"
            ];
            
            foreach ($server->getUsers() as $user) {
                if ($this->permissionBuy($user, $symbol, $symbolsPrices, $trades, $currenciesConfig)) {

                    if ($exchangeInfo == null) {
                        $exchangeInfo = $this->exchangeInfo($symbol);
                    }

                    $funds = $user->budget_coin/30;
                    
                    $size = $this->symbolSizeFormat($symbol, $funds/$symbolsPrices[$symbol], $exchangeInfo->symbols[0]->filters);
                    $body['quantity'] = $size;

                    if (Production) {
                        $request = $this->request('/api/v3/order', true, $user, $body, 'POST');
                    } else {
                        $request = $this->request('/api/v3/order/test', true, $user, $body, 'POST');
                    }

                    if ($request) {
                        $price = $request->cummulativeQuoteQty/$request->origQty;
                        $size = $request->origQty;
                        $fee = 0;
                        foreach ($request->fills as $fill) {
                            if ($fill->commissionAsset === "USDT") {
                                $fee += $fill->commission;
                            } else {
                                $fee += ($fill->commission * $fill->price);
                            }
                        }

                        array_push($users, [
                            'username' => $user->username,
                            'price' => $price,
                            'size' => $size,
                            'fee' => $fee,
                            'created_at' => date("Y-m-d H:i:s")
                        ]);
                    }
                }
            }

            if (count($users)) {
                $services->buyCoin($symbol, $users);
                $telegram->sendBuy($symbol, $users);
            }

            return true;
        }


        public function salesOrder($trade)
        {
            $server = new serverAPI();

            $body = [
                "symbol" => $trade->symbol,
                "side" => "sell",
                "type" => "market",
                "quantity" => $trade->size
            ];

            printCmd($trade);

            if (Production) {
                $request = $this->request('/api/v3/order', true, $server->getUser($trade->username), $body, 'POST');
            } else {
                $request = $this->request('/api/v3/order/test', true, $server->getUser($trade->username), $body, 'POST');
            }

            if (isset($request->symbol)) {
                $price = $request->cummulativeQuoteQty/$request->origQty;
                $fee = 0;

                foreach ($request->fills as $fill) 
                {
                    if ($fill->commissionAsset === "USDT") {
                        $fee += $fill->commission;
                    } else {
                        $fee += ($fill->commission * $fill->price);
                    }
                }

                return [
                    'price' => $price,
                    'fee' => $fee,
                    'sold_at' => time()
                ];
            }

            if ($request == -2010) {
                return [
                    'price' => 0,
                    'fee' => 0,
                    'sold_at' => time()
                ];
            }

            return false;
        }


        public function perfectSymbols($oldChanges = null, $max, $tickersPrice, $currenciesConfig, $trades)
        {
            $currencies = [];
            $coins = [];
            $data = [];
            $number = 0;
            
            foreach ($currenciesConfig as $currency) {
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

            foreach ($tickersPrice as $symbol) {
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
                                $this->newOrder($key, $tickersPrice, $trades, $currenciesConfig);
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


        public function isSellCoin($trades, $oldChanges = [], $getCurrenciesConfig, $tickersPrice)
        {
            $telegram = new telegramAPI();
            $services = new Services();
            $currenciesConfig = [];
            $changes = [];
            $prices = [];

            foreach ($getCurrenciesConfig as $currencyConfig) {
                $currenciesConfig[$currencyConfig->symbol] = $currencyConfig;
            }

            foreach ($tickersPrice as $tickerPrice) {
                $prices[$tickerPrice->symbol] = $tickerPrice->price;
            }

            foreach ($trades as $trade) {
                $currentPrice = $prices[$trade->symbol];
                
                $change = number_format((($currentPrice*100)/$trade->price) - 100, 2);
                $changes[$trade->id] = $change;

                if (isset($oldChanges[$trade->id])) {
                    $currencyConfig = $currenciesConfig[$trade->symbol];
                    $sell_up = $currencyConfig->ratio_sell;
                    $sell_down = $currencyConfig->execution_sell;

                    if ($oldChanges[$trade->id] >= $sell_up && $change - $oldChanges[$trade->id] < 0) {
                        $changes[$trade->id] = $oldChanges[$trade->id];

                        if ($change - $oldChanges[$trade->id] <= ($sell_down - (2*$sell_down))) {
                            $order = $this->salesOrder($trade);
                            if ($order) {
                                $services->sellCoin($trade->id, $order);
                                if ($order['price']) {
                                    $telegram->sendSell($trade->symbol, $trade->username);
                                }
                            }
                        }
                    }
                }
            }

            return $changes;
        }
    }