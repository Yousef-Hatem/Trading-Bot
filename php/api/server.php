<?php

    class serverAPI {

        public function request($route) {
            $API = new API();
            $telegram = new telegramAPI();

            $url = "http://207.148.65.66/api".$route;
            $headers = ["YB-KEY: ". "OWQrOWtkS1ltK3grYTFNV2VZSTRzZz09"];

            $request = $API->curlGet($url, $headers);

            if (isset($request->status)) {
                if ($request->status) {
                    return $request;
                }
                
                $telegram->sendError($request->error, $route);
                return false;
            } else {
                printCmd($request, 'request');
                return false;
            }

        }

        public function getUsers()
        {
            return $this->request("/coinusers")->data;
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