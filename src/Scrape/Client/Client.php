<?php

namespace Scrape\Client;

/**
 * Simple wrapper to bring HTTP client, authentication, and storage together 
 */
class Client {
    protected $http_client;
    
    /**
     * Values to be used by HTTP client for the actual request
     * 
     * @param array $config
     * @param bool debug
     */
    public function __construct(array $config = array(), $debug = false) {
        // storage is required for mirroring
        if (!array_key_exists('storage', $config)) {
            throw new Exception("Missing mandatory storage config");
        }
        
        $this->http_client = new HttpClient();
        $this->http_client->setDebug($debug);
        
        foreach ($config as $param => $value) {
            
            // we must have a handler class for all options
            if (!array_key_exists('class', $value)) {
                continue;                
            }
            
            // we expect full namespace path for the handler
            // "\\Some\\Name\\Space\\Class"
            if (!class_exists($value['class'])) {
                throw new Exception("Cannot find {$value['class']} handler for $param");
            }
            
            // we may choose to rely on handler defaults for config
            if (!array_key_exists('config', $value)) {
                $value['config'] = array();
            }

            $handler = new $value['class']($value['config']);
            
            switch (strtolower($param)) { 
                case 'storage':                                        
                    $storage = new \Scrape\Storage\HttpStorage;
                    $storage->setBackend($handler);
                    $storage->setDebug($debug);

                    $this->http_client->setStorage($storage);                    
                    break;
                
                case 'auth':
                    $this->http_client->setAuth($handler);                    
                    break;
                
                default:
                    throw new Exception("Unknown config param $param, only 'storage' and 'auth' are supported");
            }
        }
    }
    
    public function setCurlTimeout($sec) {
        $this->http_client->setTimeout($sec);
        return $this;
    }
    
    public function setStorageCacheTime($sec) {
        $this->http_client->getStorage()->setCacheTime($sec);
        return $this;
    }
    
    public function get($path, array $params = array()) {
        return $this->http_client->request($path, $params, 'GET');        
    }
    
    public function post($path, array $params = array()) {
        return $this->http_client->request($path, $params, 'POST');        
    }
    
}
