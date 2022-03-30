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
                    } elseif ($request->error_code != 400) {
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

        public function sendBuy($symbol, $symbolPrice)
        {
            $msg = "<b>🤖 I just bought {$symbol} and the price was {$symbolPrice}$</b>";

            $this->sendMsg($msg, false);

            return true;
        }

        public function sendSell($symbol, $symbolPrice)
        {            
            $msg = "<b>🤖 I sold {$symbol} when it hit {$symbolPrice}$</b>";
            
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
                $gainS = '';
            }

            $gain = abs($gain);
            $gain = $gain.' '.$gainS;
            if ($msgID) {
                $msg = '$'.$price.' 🪙'." (%{$gain})";

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
            $services = new Services();
            $request = $this->request('/getUpdates', null, false, null, $services->getUpdateID() + 1);

            if (isset($request->result)) {
                $result = $request->result;
                $count = count($result);

                if ($count) {
                    $services->editUpdateID($result[$count-1]->update_id);
                    if ($result[$count-1]->message) {
                        return $result[$count-1]->message;
                    }
                }
            }
            
            return false;
        }

        public function question($text, $name)
        {
            switch ($text) {
                case 'Bot':
                    $text = "I'm with you {$name}";
                    break;

                case 'Are you with me':
                    $text = "Yes with you {$name}";
                    break;

                case 'السلام عليكم':
                case 'السلام عليكم ورحمة الله و بركاته':
                    $text = "وعليكم السلام ورحمة الله و بركاته";
                    break;
                    
                case 'Welcome':
                case 'Hello Bot':
                case 'Hello':
                case 'Hi':
                    $text = "Hello {$name}";
                    break;

                case 'How are you':
                    $text = "I am fine thank you for asking";
                    break;

                case 'Edit chat id':
                    $text = "/EDITCHATID";
                    break;
                    
                case 'Thanks':
                case 'Thank you':
                case 'Thank you Bot':
                    $text = "I'm always here for you, that's my job";
                    break;

                case 'System shutdown':
                    $text = "/EXIT";
                    break;

                case '/start':
                case 'Start':
                    $text = "/START";
                    break;
                    
                case 'Stop':
                    $text = "/STOP";
                    break;

                case 'Help':
                    $text = "Hello <b>`.$name.`</b>
You can ask for help at any time
I'm a bot working here and this system is designed for you.

You can type <b>\"Start\"</b> to start trading.

If you do not specify the maximum number of grids it will be requested when trading begins by typing <b>\"Start\"</b>

<b>Maximum grids</b> will be requested when <b>\"Start\"</b> is used for the first time and must be answered within 30 seconds or the order will be canceled

After completing the \"Maximum grids\" steps, you can use the following commands

command <b>\"Stop\"</b>
You can use this command to stop trading
It can be run again through the <b>\"Start\"</b> command normally

command <b>\"Edit max grids\"</b>
You can use this command to adjust the maximum number of grids and you must respond within 30 seconds or the request will be canceled

command <b>\"Edit chat id\"</b>
You can use this command to transfer the bot to another chat
The <b>chat id</b> must be answered within 30 seconds or the order will be canceled

command <b>\"System shutdown\"</b>
You can use this command to completely shut down the system
And be careful when using it because you will lose contact with me until the system is turned on again
It is best to use this command in case of emergency

command <b>\"Who programmed you\"</b>
You can use this command to find out the developer data
If there is any problem, you can contact him

You can use the following commands to make sure that the robot works without any problems, and they are commands that do not affect the system at all
Commands:
Bot - Hi - Hello Bot - Welcome - Are you with me - Hello - How are you - Thanks - Thank you - Thank you Bot

These are the commands that you can only send

If you need any help. Anytime, I'll be with you";
                    break;

                case 'Who programmed you':
                    $text = 'Name: <b>Yousef Hatem</b>

Email: <b>yousef26hatem@gmail.com</b>
Phone Number: <b>+201146635939</b>
<a href="https://t.me/Yousef26Hatem">Telegram</a> - <a href="https://github.com/Yousef-Hatem">GitHub</a>';
                    break;
                    
                case 'Edit max grids':
                    $text = "/EDITMAXGRIDS";
                    break;
                
                default:
                    $text = false;
                    break;
            }

            return $text;
        }

        public function reply()
        {
            global $max_grids, $chat_id;

            $services = new Services();
            $msg = $this->getUpdates();
            
            if (isset($msg->text)) 
            {
                $name = $msg->from->first_name;

                if ($msg->chat->id == $chat_id) 
                {
                    $text = explode('#', $msg->text);
                    $answer = $this->question($text[0], $name);
                    if ($answer) {
                        switch ($answer) {
                            case '/START':
                                $settings = $services->getSettings();

                                if (is_null($settings->max_grids)) {
                                    $this->sendMsg('Please send the maximum number of Grids', false);
                                    sleep(30);
                                    $reply = $this->getUpdates();
                                    if (isset($reply->text)) {
                                        $settings->max_grids = $reply->text;
                                    } else {
                                        return $this->sendMsg('Time out, please try again', false);
                                    }
    
                                    $services->editSettings($settings->bot_key, $settings->chat_id, $settings->max_grids);
    
                                    $max_grids = $settings->max_grids;
                                    $reply = null;
                                }
    
                                $services->editStatus(1);
                                $reply = "🤖 I started trading now";
                                break;
    
                            case '/STOP':
                                $services->editStatus(0);
                                $reply = "🤖 Trading suspended";
                                break;
    
                            case '/EXIT':
                                $this->sendMsg("Present {$name} the system has stopped");
                                exit();
                                break;
    
                            case '/REPORT':
                                $reply = "Sorry, but the report is not ready yet";
                                break;
    
                            case '/EDITMAXGRIDS':
                                $settings = $services->getSettings();
    
                                if (isset($settings->max_grids)) {
                                    $this->sendMsg('Please send the maximum number of Grids', false);
                                    sleep(30);
                                    $reply = $this->getUpdates();
                                    if (isset($reply->text)) {
                                        $settings->max_grids = $reply->text;
                                    } else {
                                        return $this->sendMsg('Time out, please try again', false);
                                    }
    
                                    $services->editSettings($settings->bot_key, $settings->chat_id, $settings->max_grids);
    
                                    $max_grids = $settings->max_grids;
                                    $reply = "The maximum number of Grids has changed";
                                } else {
                                    $reply = 'The maximum number of Grids to modify is not specified. Please use "Start" to register the maximum number';
                                }
                                break;

                            case '/EDITCHATID':
                                $settings = $services->getSettings();
    
                                $this->sendMsg('Please send a chat id', false);
                                sleep(30);
                                $reply = $this->getUpdates();
                                if (isset($reply->text)) {
                                    $settings->chat_id = $reply->text;
                                } else {
                                    return $this->sendMsg('Time out, please try again', false);
                                }

                                $services->editSettings($settings->bot_key, $settings->chat_id, $settings->max_grids);

                                $this->sendMsg('Chat id has changed', false);
                                $chat_id = $settings->chat_id;
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
                        $reply = "Sorry {$name}, I don't understand you";
                    }
            
                    if (isset($reply)) {
                        $this->sendMsg($reply, false, $msg->message_id);
                    }
                }
            }
        }
    }
