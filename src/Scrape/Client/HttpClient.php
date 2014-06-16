<?php

namespace Scrape\Client;

/**
 * Client to build, send, and receive HTTP and convert JSON response to array
 */
class HttpClient implements HttpClientInterface {

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
            
            if ($this->auth->getUserField()) {
                $params[$this->auth->getUserField()] = $this->auth->getUser(); 
            }
            
            if ($this->auth->getSecretField()) {
                $params[$this->auth->getSecretField()] = $this->auth->getSecret();
            }
        }

        $params = http_build_query($params);
        $path = strlen($params) ? $path . '?' . $params : $path;

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

        $error = null;
        $code = 0;
        if (is_array($response) && array_key_exists('error', $response)) {
            $error = "Got error from server: " . $response['error'];
            if (array_key_exists('status', $response)) {
                $error .= " Status: " . $response['status'];
                $code = $response['status'];
            }

        } elseif (strlen($res['error'])) {
            $error = "cURL returned error: {$res['error']}";
            $code = $res['code'];
        }

        if (strlen($error)) {
            throw new \Exception($error, $code);
        }

        if ($this->storage && $method == 'GET') {
            $this->storage->save($path, $response, $res['header']);
            
            // it is possible that response before only contained 304 and not actual response
            // so we make sure we get current response data from storage
            $response = $this->storage->getResponse();
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

