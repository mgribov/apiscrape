<?php

namespace Scrape\Client;

/**
 * Client to build, send, and receive HTTP and convert JSON response to array
 */
class HttpClient implements HttpClientInterface {

    protected static $HTTP_CODE_OK = array(200, 304);

    protected $storage;
    protected $auth;    
    protected $debug;
    protected $timeout = 10;
    
    public function setAuth(\Scrape\Auth\AuthInterface $auth) {
        $this->auth = $auth;
    }

    /**
     * Local storage for responses to allow respecting cache/etags
     * 
     * @param \Scrape\Storage\HttpStorageInterface $storage 
     */
    public function setStorage(\Scrape\Storage\HttpStorageInterface $storage) {
        $this->storage = $storage;
    }
    
    public function getStorage() {
        return $this->storage;
    }
    
    public function setTimeout($sec) {
        $this->timeout = $sec;
    }
    
    public function getTimeout() {
        return $this->timeout;
    }
    
    /**
     *
     * @param bool $v 
     */
    public function setDebug($v) {
        $this->debug = $v;
    }


    /**
     * Performs curl request against given URL with optional params and optional method
     * 
     * @param string $path - full path before any params 
     * @param array $params - associative array
     * @param string $method - default is GET
     * @return array - exact return from API as an object
     * @throws \Exception 
     */
    public function request($path, $params = array(), $method = 'GET') {
        $etag = null;
        $current = false;
        $headers = array();
                
        // determine how to handle auth for this request
        // 
        // @todo ultimately, this breaks need for an AuthInterface
        // would have to hardcode other class handling here
        if ($this->auth instanceof \Scrape\Auth\HttpBasic) {
            $headers[] = "Authorization: Basic " . base64_encode($this->auth->getUser() . ':' . $this->auth->getSecret());
            
        } elseif ($this->auth instanceof \Scrape\Auth\Url) {
            
            if ($this->auth->getUserField() && $this->auth->getUser()) {
                $params[$this->auth->getUserField()] = $this->auth->getUser(); 
            }
            
            if ($this->auth->getSecretField() && $this->auth->getSecret()) {
                $params[$this->auth->getSecretField()] = $this->auth->getSecret();
            }
        }

        $params = http_build_query($params);
        
        if (strlen($params)) {
            if (preg_match('/\?/', $path)) {
                $path .= '&' . $params; 
            } else {
                $path .= '?' . $params;
            }
        }
                
        // for GETs check local storage first
        if ($this->storage && $method == 'GET') {
            $current = $this->storage->get($path);
            
            if ($current) {
                
                if ($this->storage->isCurrent()) {
                    $this->__debug($path . ': found current copy, serving from cache');
                    return $this->storage->getResponse();
                } 

                $etag = $this->storage->getEtag();

                if ($etag) {
                    $this->__debug($path . ': found current etag will try to use it');
                    $headers[] = 'If-None-Match: "' . $etag . '"';
                }
            }
        }
        
        $res = $this->__curl($path, $method, $headers);
        $response =  json_decode($res['body'], true);

        if (strlen($res['error']) || !in_array($res['code'], self::$HTTP_CODE_OK)) {
            $this->__debug($path . ": cURL did not return a success code: {$res['code']} {$res['error']}");

        } elseif (in_array($res['code'], self::$HTTP_CODE_OK) && $this->storage && $method == 'GET') {            

            // @todo may be wrap this all in 1 call in HttpStorage
            switch ($res['code']) {
                case 200:
                    $this->__debug("got 200 for $path, saving locally");
                    $this->storage->save($path, $response, $res['header']);
                    break;

                // 304 will return empty data from server, so load object from storage and bump its cache timer
                case 304:
                    $this->__debug("got 304 for $path, bumping local cache timer");
                    $this->storage->bumpCache($path);
                    $response = $this->storage->getResponse();
                    break;
            }
                    
        }
        
        return $response;

    }

    /**
     * cURL wrapper
     * 
     * @param string $path
     * @param string $method
     * @param array $headers
     * @return array 
     */
    protected function __curl($path, $method = 'GET', array $headers = array()) {
        $ret = array();
        
        // only JSON for now
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';

        $options = array();
        $options[CURLOPT_RETURNTRANSFER] = true;
        $options[CURLOPT_HEADER] = true;
        $options[CURLOPT_TIMEOUT] = $this->timeout;
        $options[CURLOPT_URL] = $path;
        $options[CURLOPT_CUSTOMREQUEST] = $method;
        $options[CURLOPT_HTTPHEADER] = $headers;

        $curl = curl_init();
        curl_setopt_array($curl, $options); 
        
        $resp = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $ret['header'] = substr($resp, 0, $header_size);
        $ret['body'] = substr($resp, $header_size);        
        $ret['error'] = curl_error($curl);
        $ret['code'] = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return $ret;        
        
    }

    /**
     *
     * @param mixed $data 
     */
    public function __debug($data) {
        if ($this->debug) {
            var_dump($data);
        }
    }

}

