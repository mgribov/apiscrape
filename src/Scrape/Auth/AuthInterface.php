<?php

namespace Scrape\Auth;

interface AuthInterface {

    public function __construct(array $config = array());
    
    public function getUser();
    
    public function getSecret();
    
}
