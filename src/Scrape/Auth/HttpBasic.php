<?php

namespace Scrape\Auth;

class HttpBasic implements AuthInterface {
    
    protected $user;
    protected $secret;

    
    public function __construct(array $config = array()) {
        $this->user = $config['user'];
        $this->secret = $config['secret'];
    }
        
    public function getUser() {
        return $this->user;
    }
    
    public function getSecret() {
        return $this->secret;
    }
        
}