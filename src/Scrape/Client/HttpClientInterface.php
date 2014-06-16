<?php

namespace Scrape\Client;

interface HttpClientInterface {

    /**
     * Build and send HTTP request, and return response in array format 
     */
    public function request($path, $params, $method);

}
