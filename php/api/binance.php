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
            $url = API_URL . $route;

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
                    if ($data->code != -2014 && $data->code != -1022 && $data->code != -2015) {
                        if ($data->code == -1021) {
                            $this->request($route, $timestamp, $API_SECRET, $API_KEY, $parameters, $method);
                        } else {
                            $telegram->sendError($data->code.': '.$data->msg, $route);
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

            $request = $API->curlGet(API_URL . "/api/v3/time");

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
                $database = new Database();
                $trading = $database->isTrading();
                foreach ($trading as $symbol) {
                    $request->total = $request->total + 20;
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


        public function permissionBuy($user, $symbol)
        {
            foreach ($user->coins as $coin) {
                if ($coin->symbol == $symbol) {
                    $database = new Database();
                    $balance = $this->getBalance($user, true);
                    $maxNumberCoins = numberFormatPrecision($balance/600, 0);
                    $coins = [];
                    $numberDeals = 0;
                    foreach ($database->isTrading() as $trading) {
                        $index = array_search($user->username, $trading['users'], true);
                        if (($index === 0 || $index > 0)) {
                            if ($trading['currency'] == $symbol) {
                                $numberDeals++;
                            }
                            $i = array_search($symbol, $coins, true);
                            if (!($i === 0 || $i > 0)) {
                                array_push($coins, $symbol);
                            }
                        }
                    }

                    if ($numberDeals < 30 && $numberDeals > 0) {
                        return true;
                    } elseif($numberDeals == 30) {
                        return false;
                    }

                    if ($maxNumberCoins > count($coins)) {
                        return true;
                    }
                }
            }

            return false;
        }


        public function newOrder($symbol)
        {
            $server = new serverAPI();
            $telegram = new telegramAPI();
            $database = new Database();

            $users = [];
            $symbolPrice = $this->getTickersPrice($symbol)->price;
            $exchangeInfo = $this->exchangeInfo($symbol);
            $funds = 20;
            $size = $this->symbolSizeFormat($symbol, $funds/$symbolPrice, $exchangeInfo->symbols[0]->filters);
            $body = [
                "symbol" => $symbol,
                "side" => "buy",
                "type" => "market"
            ];

            if ($exchangeInfo->symbols[0]->quoteOrderQtyMarketAllowed) {
                $body['quoteOrderQty'] = $funds;
            } else {
                $body['quantity'] = $size;
            }

            foreach ($server->getUsers() as $user) {
                if ($this->permissionBuy($user, $symbol)) {
                    array_push($users, $user->username);
                    if (Production) {
                        $this->request('/api/v3/order', true, $user->secret_key, $user->api_key, $body, 'POST');
                    } else {
                        $this->request('/api/v3/order/test', true, $user->secret_key, $user->api_key, $body, 'POST');
                    }
                }
            }

            $telegram->sendBuy($symbol, $size, $symbolPrice);
            $msgID = $telegram->symbolPriceUpdate($symbolPrice, false)->result->message_id;
            $database->buyCoin($symbol, $symbolPrice, $msgID, $users);

            return true;
        }


        public function salesOrder($users, $currency, $currentPrice)
        {
            foreach ($users as $user) {
                $takerCommission = $this->tradeFees($user, $currency)[0]->takerCommission;
                $size = 20/$currentPrice;
                $fee = $takerCommission * $size;
                $size = $size - $fee;
                $size = $this->symbolSizeFormat($currency, $size);
                $body = [
                    "symbol" => $currency,
                    "side" => "sell",
                    "type" => "market",
                    "quantity" => $size
                ];
    
                if (Production) {
                    $this->request('/api/v3/order', true, $user->secret_key, $user->api_key, $body, 'POST');
                } else {
                    $this->request('/api/v3/order/test', true, $user->secret_key, $user->api_key, $body, 'POST');
                }
            }
        }


        public function perfectSymbols($oldChanges = null, $max)
        {
            $server = new serverAPI();
            $currencies = [];
            $coins = [];
            $data = [];
            $number = 0;

            foreach ($server->getCurrenciesConfig() as $currency) {
                $currencies[$currency->symbol] = [
                    'price' => $currency->price_highest,
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
                    if ($max >= $number) {
                        $change = $oldChange - $data[$key];
    
                        if ($change > 0) {
                            $currency = $currencies[$key];

                            if ($change >= $currency['buy_up']) {
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
            
            return $data;
        }


        public function isSellCoin($trading, $oldChanges = [], $oldPrices = [])
        {
            $server = new serverAPI();
            $telegram = new telegramAPI();
            $database = new Database();

            $currenciesConfig = [];
            $changes = [];
            $pricesUpdate = [];

            $tickersPrice = $this->getTickersPrice();
            $prices = [];
            foreach ($tickersPrice as $tickerPrice) {
                $prices[$tickerPrice->symbol] = $tickerPrice->price;
            }

            foreach ($server->getCurrenciesConfig() as $currencyConfig) {
                $currenciesConfig[$currencyConfig->symbol] = $currencyConfig;
            }

            foreach ($trading as $coin) {
                $currentPrice = $prices[$coin['currency']];
                
                $change = number_format((($currentPrice*100)/$coin['currency_price']) - 100, 2);
                $changes[$coin['currency'].'-'.$coin['id']] = $change;
                
                $priceUpdate = false;
                if (isset($oldPrices[$coin['currency'].'-'.$coin['id']])) {
                    if ($currentPrice != $oldPrices[$coin['currency'].'-'.$coin['id']]) {
                        $priceUpdate = true;
                    }
                }

                if ($priceUpdate) {
                    $gainPr = (($currentPrice/$coin['currency_price'])*100)-100;
                    $gain = ($gainPr*20)/100;
                    $telegram->symbolPriceUpdate($currentPrice, $coin['msg_id'], $gain, $gainPr);
                }

                $pricesUpdate[$coin['currency'].'-'.$coin['id']] = $currentPrice;

                if (isset($oldChanges[$coin['currency'].'-'.$coin['id']])) {
                    $currencyConfig = $currenciesConfig[$coin['currency']];
                    $sell_up = $currencyConfig->ratio_sell;
                    $sell_down = $currencyConfig->execution_sell;

                    if ($oldChanges[$coin['currency'].'-'.$coin['id']] >= $sell_up && $change - $oldChanges[$coin['currency'].'-'.$coin['id']] < 0) {
                        $changes[$coin['currency'].'-'.$coin['id']] = $oldChanges[$coin['currency'].'-'.$coin['id']];

                        if ($change - $oldChanges[$coin['currency'].'-'.$coin['id']] <= ($sell_down - (2*$sell_down))) {
                            $this->salesOrder($coin['users'], $coin['currency'], $currentPrice);
                            $database->sellCoin($coin['id'], $currentPrice);
                            $telegram->deleteMsg($coin['msg_id']);
                            $telegram->sendSell($coin['currency'], $currentPrice);
                        }
                    }
                }
            }

            return ['changes' => $changes, 'prices' => $pricesUpdate];
        }
    }