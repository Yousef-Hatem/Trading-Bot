<?php

    class telegramAPI {

        public function request($route, $msg = null, $disableNotification = true, $messageID = null, $offset = null) 
        {
            global $bot_key, $chat_id;

            $API = new API();
            $url = "https://api.telegram.org/bot".$bot_key.$route;

            $postdata = [
                'chat_id' => $chat_id,
                'message_id' => $messageID,
                'parse_mode' => 'HTML',
                'disable_notification' => $disableNotification,
                'text' => $msg,
                'offset' => $offset,
                'reply_to_message_id' => $messageID
            ];
            $headers = [
                sprintf("Content-Type: %s", "application/json"),
            ];

            $request = $API->curlPost($url, $headers, $postdata);

            if (isset($request->ok)) {
                if ($request->ok) {
                    return $request;
                } else {
                    if ($request->error_code == 429) {
                        sleep($request->parameters->retry_after);
                        return $this->request($route, $msg, $disableNotification, $messageID, $offset);
                    } else {
                        printCmd($request, 'request');
                        printCmd($url, 'url');
                        printCmd($postdata, 'postdata');
                    }
                }
            } else {
                return $this->request($route, $msg, $disableNotification, $messageID, $offset);
            }
        }

        public function sendMsg($msg, $disableNotification = true, $reply = null)
        {
            return $this->request('/sendMessage', $msg, $disableNotification, $reply);
        }

        public function sendBuy($symbol, $size, $symbolPrice)
        {
            $msg = "<b>🤖 I just bought {$size} {$symbol} and the price was {$symbolPrice}$</b>";

            $this->sendMsg($msg, false);

            return true;
        }

        public function sendSell($symbol, $symbolPrice)
        {            
            $msg = "<b>🤖 I sold {$symbol} when it hit {$symbolPrice}$";
            
            $this->sendMsg($msg, false);

            return true;
        }

        public function sendError($msg, $URL)
        {
            
            $msg = "<b>🤖 Something went wrong during my work ❌</b>

Error: ".$msg.'

URL: '.$URL;
            
            $this->sendMsg($msg, false);

            return true;
        }

        public function editMessageText($msgID, $msg)
        {
            return $this->request('/editMessageText', $msg, true, $msgID);
        }

        public function symbolPriceUpdate($price, $msgID = false, $gain = null)
        {
            $gain = number_format($gain, 4);
            if ($gain != 0) {
                $gainS = ($gain > 0)? "▲": "▼";
            } else {
                $gainS = "-";
            }

            $gain = abs($gain);
            $gain = $gain.' '.$gainS;
            if ($msgID) {
                $msg = '$'.$price.' 🪙'."
(%{$gain})";

                return $this->editMessageText($msgID, $msg);
            }

            return $this->sendMsg('$'.$price.' 🪙', true);
        }

        public function deleteMsg($msgID)
        {
            return $this->request('/deleteMessage', null, true, $msgID);
        }

        public function getUpdates()
        {
            $database = new Database();
            $request = $this->request('/getUpdates', null, false, null, $database->getUpdateID() + 1);

            if (isset($request->result)) {
                $result = $request->result;
                $count = count($result);

                if ($count) {
                    $database->updateID($result[$count-1]->update_id);
                    return $result[$count-1]->message;
                }
            }
            
            return false;
        }

        public function reply()
        {
            global $max_grids, $chat_id;

            $database = new Database();
            $msg = $this->getUpdates();

            if (isset($msg->text)) {
                if ($msg->chat->id == $chat_id) {
                    $text = explode('#', $msg->text);
                    $answer = $database->question($text[0]);
                    if ($answer) {
                        switch ($answer) {
                            case '/START':
                                $user = $database->getSettings();

                                if (is_null($user['max_grids'])) {
                                    $this->sendMsg('Please send the maximum number of Grids', false);
                                    sleep(30);
                                    $reply = $this->getUpdates();
                                    if (isset($reply->text)) {
                                        $user['max_grids'] = $reply->text;
                                    } else {
                                        return $this->sendMsg('Time out, please try again', false);
                                    }
    
                                    $database->updateSettings($user['bot_key'], $user['chat_id'], $user['max_grids']);
    
                                    $max_grids = $user['max_grids'];
                                    $reply = null;
                                }
    
                                $database->editStatus(1);
                                $reply = "🤖 I started trading now";
                                break;
    
                            case '/STOP':
                                $database->editStatus(0);
                                $reply = "🤖 Trading suspended";
                                break;
    
                            case '/EXIT':
                                $this->sendMsg('Present Herly the system has stopped');
                                exit();
                                break;
    
                            case '/REPORT':
                                $reply = "Sorry, but the report is not ready yet";
                                // $reply = $this->report();
                                break;
    
                            case '/EDITMAXGRIDS':
                                $user = $database->getSettings();
    
                                if (isset($user['max_grids'])) {
                                    $this->sendMsg('Please send the maximum number of Grids', false);
                                    sleep(30);
                                    $reply = $this->getUpdates();
                                    if (isset($reply->text)) {
                                        $user['max_grids'] = $reply->text;
                                    } else {
                                        return $this->sendMsg('Time out, please try again', false);
                                    }
    
                                    $database->updateSettings($user['bot_key'], $user['chat_id'], $user['max_grids']);
    
                                    $max_grids = $user['max_grids'];
                                    $reply = "The maximum number of Grids has changed";
                                } else {
                                    $reply = 'The maximum number of Grids to modify is not specified. Please use "Start" to register the maximum number';
                                }
                                break;

                            case '/EDITCHATID':
                                $user = $database->getSettings();
    
                                $this->sendMsg('Please send a chat id', false);
                                sleep(30);
                                $reply = $this->getUpdates();
                                if (isset($reply->text)) {
                                    $user['chat_id'] = $reply->text;
                                } else {
                                    return $this->sendMsg('Time out, please try again', false);
                                }

                                $database->updateSettings($user['bot_key'], $user['chat_id'], $user['max_grids']);

                                $this->sendMsg('Chat id has changed', false);
                                $chat_id = $user['chat_id'];
                                $this->sendMsg('I will be here with you now', false);
                                $reply = null;
                                break;
                            
                            default:
                            $answer = explode('<msg>', $answer);
                            if (count($answer) > 1) {
                                foreach ($answer as $msg) {
                                    $this->sendMsg($msg);
                                }
                            } else {
                                $reply = $answer[0];
                            }
                                break;
                        }
                    } else {
                        $reply = "Sorry Herly, I don't understand you";
                    }
            
                    if (isset($reply)) {
                        $this->sendMsg($reply, false, $msg->message_id);
                    }
                } else {
                    printCmd($msg, 'msg');
                }
            }
        }

        public function balanceNotEnough($user, $balance)
        {
            return $this->sendMsg("The purchase of the currency for <b>\"{$user->username}\"</b> failed because his balance (".'$'."{$balance}) is less than the total budget (".'$'."{$user->total_budget})", false);
        }

        public function dailyReport($date, $prices = null, $orders = null, $trading = null)
        {
            $binance = new binanceAPI();
            $database = new Database();

            $totalTrades = 0;

            $successfulTrades = 0;
            $losingTrades = 0;

            $profitAmount = 0;
            $lossAmount = 0;

            if (!isset($prices)) $prices = $binance->getcurrenciePrice();
            if (!isset($orders)) $orders = $database->getOrders();
            if (!isset($trading)) $trading = $database->isTrading();
            
            foreach ($orders as $order) {
                if ($date == explode(' ', $order['date'])[0] || $date === "all") {
                    $orderSales = $order['sales'];(((1200/1000)*200)-200) - 0.2;
                    $totalTrades++;
                    $gain = ((($orderSales['current_price']/$order['currency_price'])*$order['amount_paid'])-$order['amount_paid']) - $orderSales['fee'];
                    
                    if ($gain > 0) {
                        $successfulTrades++;
                        $profitAmount = $profitAmount + $gain;
                    } else {
                        $losingTrades++;
                        $lossAmount = $lossAmount + abs($gain);
                    }
                }
            }
            
            foreach ($trading as $order) {                
                if ($date == explode(' ', $order['date'])[0] || $date === "all") {
                    $totalTrades++;
                    $currentPrice = $prices[$order['currency'].'USDT'];
                    $gain = (($currentPrice/$order['currency_price'])*$order['amount_paid'])-$order['amount_paid'];
    
                    if ($currentPrice > $order['currency_price']) {
                        $successfulTrades++;
                        $profitAmount = $profitAmount + $gain;
                    } else {
                        $losingTrades++;
                        $lossAmount = $lossAmount + abs($gain);
                    }
                }
            }

            if ($totalTrades) {
                $successfulTradesPr = ($successfulTrades/$totalTrades)*100;
                $losingTradesPr = ($losingTrades/$totalTrades)*100;    
    
                $netProfit = $profitAmount - $lossAmount;
    
                $successfulTradesPr = number_format($successfulTradesPr, 2);
                $losingTradesPr = number_format($losingTradesPr, 2);
                $profitAmount = number_format($profitAmount, 2);
                $lossAmount = number_format($lossAmount, 2);
                $netProfit = number_format($netProfit, 2);
    
                $report = "Total Trades: {$totalTrades}
Successful Trades: {$successfulTrades} ({$successfulTradesPr}%)
Losing Trades: {$losingTrades} ({$losingTradesPr}%)

Profit Value: {$profitAmount}$
Loss Value: {$lossAmount}$ 

Net Profit: {$netProfit}$";
    
                return $report;
            }

            return false;
        }

        public function report()
        {
            $binance = new binanceAPI();
            $database = new Database();

            $prices = $binance->getcurrenciePrice();
            $orders = $database->getOrders();
            $trading = $database->isTrading();

            $report = '';
            $dates = [];

            foreach ($orders as $order) {
                $date = explode(' ', $order['date'])[0];
                
                $index = array_search($date, $dates, true);
                if (!($index === 0 || $index > 0)) {
                    array_push($dates, $date);
                }
            }

            foreach ($trading as $order) {
                $date = explode(' ', $order['date'])[0];
                
                $index = array_search($date, $dates, true);
                if (!($index === 0 || $index > 0)) {
                    array_push($dates, $date);
                }
            }

            foreach ($dates as $date) {
                $dailyReport = $this->dailyReport($date, $prices, $orders, $trading);
                $inconstant = '';

                if ($date == date('Y-m-d')) {
                    $inconstant = "  <i>(inconstant)</i>";
                }

                $date = explode('-', $date);
                $report = $report."

                
<b>Day ".$date[1].'/'.$date[2].'</b>'.$inconstant."
"               .$dailyReport.'
------------------------------
';

            }
            
            $dailyReport = $this->dailyReport("all", $prices, $orders, $trading);
            $report = $report."
<b>Report Summary:</b>

"               .$dailyReport;

            return $report;
        }
    }
