<?php 
    
    class Database {
        
        public function connect()
        {
            $conn = new mysqli(DATABASE['SERVERNAME'], DATABASE['USERNAME'], DATABASE['PASSWORD'], DATABASE['DB']);

            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
                return $this->connect();
            }
            
            return $conn;
        }

        public function query($query)
        {
            $conn = $this->connect();
            $conn->query("SET NAMES utf8");
            $conn->query("SET CHARACTER SET utf8");
            $sql = $conn->query($query);

            if ($conn->error) {
                print_r("Query: " . $query . "\nError: " . $conn->error);
                exit();
            }
            
            return $sql;
        }

        public function insertData($table, $data) 
        {
            $columns = '';
            $values = '';

            $data['date'] = date('Y-m-d h:i:s');

            foreach ($data as $key => $value) {
                if ($columns) {
                    $columns .= ", `{$key}`";
                    $values .= ", '{$value}'";
                } else {
                    $columns = "`{$key}`";
                    $values = "'{$value}'";
                }
            }

            $sql = $this->query("INSERT INTO `{$table}` ({$columns}) VALUES ({$values})");
            
            return $sql;
        }

        public function getData($query) 
        {
            $data = [];
            $result = $this->query("SELECT {$query}");

            while ($row = $result->fetch_assoc()) {
                array_push($data, $row);
            }

            return $data;
        }

        public function getSettings()
        {
            return $this->getData("* FROM settings WHERE id = 1")[0];
        }

        public function updateSettings($BOT_KEY, $chat_id, $max_grids)
        {
            if (is_null($max_grids)) {
                $max_grids  = 'NULL';
            }
            
            return $this->query("UPDATE settings SET 
                BOT_KEY = '{$BOT_KEY}',
                chat_id = {$chat_id},
                max_grids = {$max_grids}
            WHERE id = 1");
        }

        public function getStatus()
        {
            return $this->getData("status FROM settings WHERE id = 1")[0]['status'];
        }

        public function editStatus($status)
        {
            return $this->query("UPDATE settings SET `status` = {$status} WHERE id = 1");
        }

        public function buyCoin($currency, $currencyPrice, $msgID, $users)
        {
            return $this->insertData('purchase_orders', [
                'currency' => $currency,
                'currency_price' => $currencyPrice,
                'users' => json_encode($users),
                'msg_id' => $msgID
            ]);
        }

        public function sellCoin($idPurchaseOrder, $currentPrice)
        {
            return $this->insertData('sales_orders', [
                'id_purchase_order' => $idPurchaseOrder,
                'current_price' => $currentPrice
            ]);
        }

        public function getOrders()
        {
            $sql = "* FROM purchase_orders";
            $data = $this->getData($sql);
            $orders = [];

            for ($i=0; $i < count($data); $i++) { 
                $order = $data[$i];
                $id = $order['id'];

                $orderData = $this->getData("* FROM sales_orders WHERE id_purchase_order = {$id}");
                
                if (count($orderData)) {
                    $order['sales'] = $orderData[0];
                    array_push($orders, $order);
                }
            }

            return $orders;
        }

        public function isTrading($currency = null)
        {
            isset($currency)? $text = " WHERE currency = '{$currency}'": $text = '';

            $data = [];
            $currencies = $this->getData("* FROM purchase_orders{$text}");

            foreach ($currencies as $currency) {
                $id = $currency['id'];
                if (!count($this->getData("* FROM sales_orders WHERE id_purchase_order = {$id}"))) {
                    $currency['users'] = json_decode($currency['users']);
                    array_push($data, $currency);
                }
            }
            
            return $data;
        }

        public function updateID($updateID)
        {
            return $this->query("UPDATE settings SET reply_to_message = $updateID WHERE id = 1");
        }

        public function getUpdateID()
        {
            $updateID = $this->getData("reply_to_message FROM settings WHERE id = 1")[0]['reply_to_message'];

            return $updateID;
        }

        public function question($question)
        {
            $answer = $this->getData("answer FROM chat WHERE question = '{$question}'");
            
            if (count($answer)) {
                return $answer[0]['answer'];
            }

            return false;
        }

        public function getUsers()
        {
            return $this->getData("* FROM users");
        }

        public function updateUsers()
        {
            $server = new serverAPI();
            $serverUsers = [];
            $users = [];
            $addUsers = "";

            foreach ($this->getUsers() as $user) {
                $users[$user['username']] = $user['username'];
            }

            foreach ($server->getUsers() as $serverUser) {
                $serverUsers[$serverUser->username] = $serverUser->username;
            }

            foreach ($serverUsers as $serverUser) {
                if (!isset($users[$serverUser])) {
                    $addUsers .= "('{$serverUser}'),";
                }
            }
            if ($addUsers != '') {
                $addUsers = substr_replace($addUsers ,"",-1);
                $this->query("INSERT INTO users (`username`) VALUES {$addUsers}");
            }

            foreach ($users as $user) {
                if (!isset($serverUsers[$user])) {
                    $this->query("DELETE FROM users WHERE username = '{$user}'");
                }
            }

        }

    }