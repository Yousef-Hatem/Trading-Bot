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
            $grid = 0;

            foreach ($this->isTrading() as $trading) {
                if ($trading['currency'] == $currency) {
                    $grid++;
                }
            }

            return $this->insertData('purchase_orders', [
                'currency' => $currency,
                'currency_price' => $currencyPrice,
                'grid' => $grid,
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
            $orders = $this->getData('* FROM purchase_orders');
            $count = count($orders);

            for ($i=0; $i < $count; $i++) {
                $orderData = $this->getData("* FROM sales_orders WHERE id_purchase_order = {$orders[$i]['id']}");
                
                if (count($orderData)) {
                    $orders[$i]['users'] = json_decode($orders[$i]['users']);
                    $orders[$i]['sales'] = $orderData[0];
                } else {
                    unset($orders[$i]);
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

        public function report()
        {
            $report = [];
            $server = new serverAPI();
            $users = $server->getUsers();
            $orders = $this->getOrders();

            foreach ($users as $user) {
                $total_profits = 0;
                $currencies = [];
                $symbols = [];

                foreach ($orders as $order) {
                    $i1 = array_search($user->username, $order['users'], true);
                    $i2 = array_search($order['currency'], $currencies, true);

                    if (($i1 === 0 || $i1 > 0) && !($i2 === 0 || $i2 > 0)) {
                        array_push($currencies, $order['currency']);

                        $symbol = [
                            'symbol' => $order['currency'],
                            'total_profits' => 0,
                            'grids' => [],
                        ];

                        foreach ($orders as $order) {
                            $i = array_search($user->username, $order['users'], true);

                            if (($i === 0 || $i > 0) && $symbol['symbol'] == $order['currency']) {
                                $grid = [
                                    'grid' => $order['grid'],
                                    'earning' => number_format(((($user->budget_coin/30)/$order['currency_price']) * $order['sales']['current_price']) - ($user->budget_coin/30), 4),
                                    'selling_price' => $order['sales']['current_price']
                                ];

                                array_push($symbol['grids'], $grid);

                                $symbol['total_profits'] += $grid['earning'];
                            }
                        }

                        $symbol['total_profits'] = number_format($symbol['total_profits'], 4);

                        array_push($symbols, $symbol);

                        $total_profits += $symbol['total_profits'];
                    }
                }

                $data = [
                    "username" => $user->username,
                    "total_profits" => number_format($total_profits, 4),
                    "symbols" => $symbols
                ];

                array_push($report, $data);
            }

            return $report;
        }

    }