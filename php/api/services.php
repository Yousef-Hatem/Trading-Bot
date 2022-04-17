<?php 
 
    class Services {
        
        public function request($route, $method = "GET", $body = []) {
            $API = new API();

            $url = SERVICES_API_URL.$route;
            $headers = [sprintf("Content-Type: %s", "application/json"), "Accept: application/json", "AT-KEY: ". AT_KEY];

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
                    echo "\n\n";
                    echo "Error: ".$request->error;
                    echo "\nURL: ".$url;
                    echo "\n\n";
                    return false;
                }
                
                if (isset($request->data)) {
                    return $request->data;
                }

                unset($request->status);
                
                return $request;
            } elseif (isset($request->message)) {
                if ($request->message == "Too Many Attempts") {
                    sleep(3);
                    return $this->request($route, $method, $body);
                }
                echo "\n\n";
                echo "Error: ".$request->message;
                echo "\nURL: ".$url;
                echo "\n\n";
                return false;

            } elseif ($method == "PUT") {
                return $request;
            } else {
                printCmd($route, 'route');
                printCmd($method, 'method');
                printCmd($body, 'body');
                printCmd($request, 'request');
                return $this->request($route, $method, $body);
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

        public function buyCoin($symbol, $users)
        {
            return $this->request('/buy', 'POST', [
                'symbol' => $symbol,
                'users' => $users
            ]);
        }

        public function sellCoin($id, $order)
        {
            return $this->request("/sell/{$id}?price={$order['price']}&fee={$order['fee']}&sold_at={$order['sold_at']}", 'PUT');
        }

        public function isTrading()
        {
            $trades = $this->request("/trades");

            if (is_array($trades)) {
                return $trades;
            }

            return $this->isTrading();
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