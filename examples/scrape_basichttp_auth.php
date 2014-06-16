<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

// set $api_token and $api_secret
include 'auth.php';

// set storage and auth details
// using \Scrape\Auth\Url in this case will append auth data to each request
$config = array(
    'storage' => array(
        'class' => "\\Scrape\\Storage\\Backend\\Mongo",
        'config' => array(
            'connection' => 'mongodb://127.0.0.1:27017',
            'database' => 'apiscrape',
            'collection' => 'api',
            )
        ),
    'auth' => array(
        // this will append HTTP Basic auth headers
        'class' => '\\Scrape\\Auth\\HttpBasic',
        'config' => array(
            'user' => $api_token,
            'secret' => $api_secret
            ),
        ),
    );


$cli = new \Scrape\Client\Client($config, true);

$data = $cli
        ->setCurlTimeout(20) // their api may be slow
        ->setStorageCacheTime(3600) // their api may not return Cache-Control headers
        ->get('https://api.httpbasic.com/object/1234');

var_dump($data);
    
