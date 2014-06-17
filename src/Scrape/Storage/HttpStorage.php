<?php

namespace Scrape\Storage;

/**
 * Manages a list of saved paths, their responses and cache/etag data 
 */
class HttpStorage implements HttpStorageInterface {
    
    protected $debug = false;
    
    protected $backend;
    
    /**
     * if no Cache-Control received, and no other value configured, cache for 1 day by default
     * received Cache-Control will overwrite this
     * @var type 
     */
    protected $cacheTime = 86400;
    
    /**
     * Object format
     * @var array
     */
    protected $object = array(
        'path' => null,
        'cache' => null,
        'etag' => null,
        'response' => null,
        );
    
    /**
     *
     * @param bool $v 
     */
    public function setDebug($v) {
        $this->debug = $v;
    }
    
    public function setCacheTime($sec) {
        $this->cacheTime = $sec;
    }
    
    public function getCacheTime() {
        return $this->cacheTime;
    }
    
    /**
     *
     * @param \Scrape\Storage\Backend\BackendInterface $b 
     */
    public function setBackend(\Scrape\Storage\Backend\BackendInterface $b) {
        $this->backend = $b;
    }
    
    /**
     *
     * @return integer
     */
    public function getCache() {
        return $this->object['cache'];
    }
    
    /**
     *
     * @return string
     */
    public function getEtag() {
        if (array_key_exists('etag', $this->object)) {
            return $this->object['etag']; 
        }
        
        return null;
    }
    
    /**
     *
     * @return array
     */
    public function getResponse() {
        return $this->object['response'];
    }
    
    /**
     *
     * @return bool
     */
    public function isCurrent() {
        return $this->object['cache'] > time();
    }

    /**
     *
     * @param string $path
     * @return mixed 
     */
    public function remove($path) {
        return $this->backend->delete($path);
    }
    
    /**
     *
     * @param string $path
     * @return array 
     */
    public function get($path) {
        $this->object = $this->backend->get($path);        
        return $this->object;
    }
    
    /**
     * 
     * @param string $path
     * @param array $response
     * @param string $header
     * @return array 
     */
    public function save($path, $response, $header) {
        $all_headers = array();
        $cache_headers = array();
        
        // collect all headers in nicer format
        $headers = explode("\r\n", $header);
        foreach ($headers as $h) {
            $a = explode(':', $h);
            if (count($a) == 2) {
                $all_headers[trim($a[0])] = trim($a[1]);
            }
        }

        $etag = array_key_exists('Etag', $all_headers) ? preg_replace('/"/', '', $all_headers['Etag']) : null;
        
        // get max-age        
        if (array_key_exists('Cache-Control', $all_headers)) {
            preg_match('/max-age=(\d+)/', $all_headers['Cache-Control'], $cache_headers);            
        }

        $this->object['path'] = $path;
        $this->object['response'] = $response;             
        
        if (count($cache_headers)) {
            $this->__debug("got cache-control for $path, cache is valid for {$cache_headers[1]} seconds");
            $this->object['cache'] = (int)(time() + $cache_headers[1]);
            
        } else {
            $this->__debug("no cache-control for $path, caching for {$this->cacheTime} seconds");
            $this->object['cache'] = (int)(time() + $this->cacheTime);            
        }

        // @todo in both cases, we currently delete the object from storage and put a new one
        if (strlen($etag) && (!array_key_exists('etag', $this->object) || (array_key_exists('etag', $this->object) && $etag != $this->object['etag']))) {
            $this->__debug("new etag for $path, saving locally");            
            $this->object['etag'] = $etag;
            
        } elseif (in_array("HTTP/1.1 304 Not Modified", $headers)) {
            $this->__debug("got 304 for etag for $path, bumping local cache timer");
            
        } else {
            $this->__debug("no 304 and no etag for $path, saving locally");
        }

        $this->backend->delete($path);
        $this->backend->put($this->object);
        
        return $this->object;
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
