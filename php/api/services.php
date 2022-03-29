<?php 
 
    class Services {
        
        public function request($route, $method = "GET", $body = []) {
            $API = new API();
            $telegram = new telegramAPI();

            $url = SERVICES_API_URL.$route;
            $headers = [sprintf("Content-Type: %s", "application/json"), "AT-KEY: ". AT_KEY];

            switch ($method) {
                case 'GET':
                    $request = $API->curlGet($url, $headers);
                    break;
                case 'POST':
                    $request = $API->curlPost($url, $headers, $body);
                    break;
                case 'DELETE':
                    $request = $API->curlDelete($url, $headers);
                    break;
                case 'PUT':
                    $request = $API->curlPut($url, $headers);
                    break;
                
                default:
                    $request = "Method";
                    break;
            }

            if (isset($request->status)) {
                if (!$request->status) {
                    $telegram->sendError($request->error, $url);
                    return false;
                }
                
                if (isset($request->data)) {
                    return $request->data;
                }

                unset($request->status);
                
                return $request;
            } elseif ($method == "PUT") {
                return true;
            } else {
                printCmd($route, 'route');
                printCmd($method, 'method');
                return "Error";
            }

        }

        public function getSettings()
        {
            return $this->request('/settings');
        }

        public function editSettings($bot_key, $chat_id, $max_grids)
        {
            return $this->request("/settings?bot_key={$bot_key}&chat_id={$chat_id}&max_grids={$max_grids}", 'PUT');
        }

        public function getStatus()
        {
            return $this->request("/status")->trading_status;
        }

        public function editStatus($status)
        {
            return $this->request("/status/{$status}", 'PUT');
        }

        public function buyCoin($symbol, $price, $msgID, $users)
        {
            return $this->request('/buy', 'POST', [
                'symbol' => $symbol,
                'price' => $price,
                'users' => $users,
                "msg_id" => $msgID
            ]);
        }

        public function sellCoin($id, $price)
        {
            return $this->request("/sell/{$id}?price={$price}", 'PUT');
        }

        public function isTrading()
        {
            return $this->request("/trades");
        }

        public function getUpdateID()
        {
            return $this->request("/reply-to-message")->reply_to_message;
        }

        public function editUpdateID($updateID)
        {
            return $this->request("/reply-to-message/{$updateID}", "PUT");
        }
    }