<?php

namespace Scrape\Auth;

class Url implements AuthInterface {
    
    protected $userField = 'key';
    protected $secretField = 'secret';
    
    protected $user;
    protected $secret;

    
    public function __construct(array $config = array()) {
        if (array_key_exists('user_field', $config)) {
            $this->userField = $config['user_field'];
        }
        
        if (array_key_exists('secret_field', $config)) {
            $this->userField = $config['secret_field'];
        }
        
        $this->user = $config['user'];
        $this->secret = $config['secret'];
    }
        
    public function getUser() {
        return $this->user;
    }
    
    public function getSecret() {
        return $this->secret;
    }
    
    public function getUserField() {
        return $this->userField;
    }
    
    public function getSecretField() {
        return $this->secretField;
    }
    
}