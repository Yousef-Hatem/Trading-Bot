<?php

    class API {

        public function curl($url, $method, $headers = false, $body = []) 
        {
            $state_ch = curl_init();

            curl_setopt($state_ch, CURLOPT_URL, $url);

            curl_setopt($state_ch, CURLOPT_RETURNTRANSFER, true);

            if ($method === "POST") {
                curl_setopt($state_ch, CURLOPT_POST, true);

                if (count($body)) {
                    $body = json_encode($body);
    
                    curl_setopt($state_ch,CURLOPT_POSTFIELDS, $body);
                }
            }

            if ($method === "DELETE") {
                curl_setopt($state_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            }

            if ($headers) {
                curl_setopt($state_ch, CURLOPT_HTTPHEADER, $headers);
            }

            $state_result = curl_exec($state_ch);

            return json_decode($state_result);
        }

        public function curlGet($url, $headers = false) 
        {
            return $this->curl($url, "GET", $headers);
        }

        public function curlPost($url, $headers = false, $body = []) 
        {
            return $this->curl($url, "POST", $headers, $body);
        }

        public function curlDelete($url, $headers = false)
        {
            return $this->curl($url, "DELETE", $headers);
        }

    }