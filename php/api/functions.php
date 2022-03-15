<?php

    class API {

        public function curl($url, $method, $headers = false, $body = [], $proxy = null) 
        {
            $state_ch = curl_init();

            curl_setopt($state_ch, CURLOPT_URL, $url);

            if (is_string($proxy) && $proxy != "main") {
                curl_setopt($state_ch, CURLOPT_PROXY, $proxy);
            }

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

            if (is_string($proxy) && $proxy != "main") {
                if ($state_result == '') {
                    return $this->curl($url, $method, $headers, $body, $proxy);
                }
            }

            return json_decode($state_result);
        }

        public function curlGet($url, $headers = false, $proxy = null) 
        {
            return $this->curl($url, "GET", $headers, [], $proxy);
        }

        public function curlPost($url, $headers = false, $body = [], $proxy = null) 
        {
            return $this->curl($url, "POST", $headers, $body, $proxy);
        }

        public function curlDelete($url, $headers = false, $proxy = null)
        {
            return $this->curl($url, "DELETE", $headers, [], $proxy);
        }

    }