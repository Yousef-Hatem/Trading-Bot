<?php

    class serverAPI {

        public function request($route) {
            $API = new API();

            $url = "http://207.148.65.66/api".$route;
            $headers = ["YB-KEY: ". AT_KEY];

            $request = $API->curlGet($url, $headers);

            if (isset($request->status)) {
                if ($request->status) {
                    return $request;
                }
                
                printCmd($request->message, 'Error');
                printCmd($url, 'URL');
                return false;
            } else {
                return $this->request($route);
            }

        }

        public function getUsers()
        {
            return $this->request("/coinusers")->data;
        }

        public function getUser($username)
        {
            return $this->request("/coinusers/{$username}")->data;
        }

        public function getCurrenciesConfig()
        {
            $request = $this->request("/coinsetting");
            
            if (isset($request->data)) {
                return $request->data;
            } else {
                return $this->getCurrenciesConfig();
                printCmd($request);
            }
        }
    }