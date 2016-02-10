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
     * @return mixed
     */
    public function getCache() {
        if (!is_null($this->object['response'])) {
            return $this->object['cache'];
        }

        return false;
    }
    

    /**
     *
     * @return mixed
     */
    public function getEtag() {
        if (is_array($this->object['response']) && count($this->object['response']) > 0 && array_key_exists('etag', $this->object)) {
            return $this->object['etag']; 
        }
        
        return false;
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
        if ($this->cacheTime == 0) {
            return false;
        }

        return (is_array($this->object['response']) && count($this->object['response']) > 0 && $this->object['cache'] > time());
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
        if ($this->cacheTime == 0) {
            $this->backend->delete($path);

            return [
                'path' => null,
                'cache' => null,
                'etag' => null,
                'response' => null,
            ];
        }

        $this->object = $this->backend->get($path);        
        return $this->object;
    }
    
    /**
     * Just bump up cache timer, used when handling 304's
     *
     * @param string $path
     * @return bool
     */
    public function bumpCache($path) {
        $this->get($path);

        if (is_array($this->object['response']) && count($this->object['response']) > 0) {
            // @todo whats a good default?
            $this->object['cache'] = (int)(time() + 3600);
            $this->backend->delete($path);
            $this->backend->put($this->object);
            return true;
        }

        return false;
    }

    /**
     * Create or replace a current object by its path
     *
     * @param string $path
     * @param array $response
     * @param string $header
     * @return bool
     */
    public function save($path, $response, $header) {
        if (!(is_array($response) && count($response) > 0)) {
            $this->__debug("no valid response for $path, will not save, invalidating any saved copy");            
            $this->backend->delete($path);
            return false;
        }

        if ($this->cacheTime == 0) {
            return false;
        }

        $this->object['path'] = $path;
        $this->object['response'] = $response;             
 
        // parse response headers
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
        
        // get max-age        
        if (array_key_exists('Cache-Control', $all_headers)) {
            preg_match('/max-age=(\d+)/', $all_headers['Cache-Control'], $cache_headers);            
        }

        if (count($cache_headers)) {
            $this->__debug("got cache-control for $path, cache is valid for {$cache_headers[1]} seconds");
            $this->object['cache'] = (int)(time() + $cache_headers[1]);
            
        } else {
            $this->__debug("no cache-control for $path, caching for {$this->cacheTime} seconds");
            $this->object['cache'] = (int)(time() + $this->cacheTime);            
        }

        // get etag
        $etag = array_key_exists('Etag', $all_headers) ? preg_replace('/"/', '', $all_headers['Etag']) : null;
        if (strlen($etag)) {
            $this->__debug("new etag for $path");            
            $this->object['etag'] = $etag;
            
        } else {
            $this->__debug("no etag for $path");
        }

        $this->backend->delete($path);
        $this->backend->put($this->object);
        
        return true;
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
