# JSON API scraper with local cache

## Motivation - a transparent GET cache
I've encountered several APIs which were not as complex as Twitter or Facebook, but nice enough to utilize JSON and HTTP caching (Etag and Cache-Control) for GET requests.   

I needed to scrape them periodically and store their results somewhere for later exploration/use.   

[apiscrape](https://github.com/mgribov/apiscrape) does just that - takes a URL with optional (but primitive for now) authentication, and stores the GET responses locally for later usage.  
This library can also be used for real-time requests as well of course, not just scraping, and still provide local cache benefits.

It uses [MongoDB](http://www.php.net/manual/en/book.mongo.php) as the cache layer, so JSON is stored exactly as it is returned.  
It wraps [cURL](http://www.php.net/manual/en/book.curl.php), so it supports all other HTTP verbs as well, but will cache responses only for GETs.  

## Limitations - no OAuth
Currently, only HTTP Basic and URL authentication is supported.  
HTTP Basic authentication adds "Authorization: Basic" HTTP header.  
URL authentication simply appends a secret to each request URL. (ex: ?secret=1234)  
See [src/Scrape/Auth](https://github.com/mgribov/apiscrape/tree/master/src/Scrape/Auth) for details

## Install via Composer into existing project
    curl -sS https://getcomposer.org/installer | php # if composer is not installed
    ./composer.phar require mgribov/apiscrape

## Examples
For a real-world complex example which inspired this library see [Triptelligent PHP API wrapper](https://github.com/mgribov/php-triptelligent)

See [examples/](https://github.com/mgribov/apiscrape/tree/master/examples) folder for all use cases  
Make sure you install composer and run "./composer.phar dump-autoload" first to use the examples if you just want to play around with it

    <?php
    // scrape some simple API with HTTP Basic auth
    $api_token = '12345';
    $api_secret = 'abcde';

    $config = array(
        // configure mongo as local cache - required
        'storage' => array(
            'class' => "\\Scrape\\Storage\\Backend\\Mongo",
            'config' => array(
                'connection' => 'mongodb://127.0.0.1:27017',
                'database' => 'apiscrape',
                'collection' => 'api',
                )
            ),
        // configure HTTP Basic auth - optional
        'auth' => array(            
            'class' => '\\Scrape\\Auth\\HttpBasic',
            'config' => array(
                'user' => $api_token,
                'secret' => $api_secret
                ),
            ),
        );

    $debug = true;
    $cli = new \Scrape\Client\Client($config, $debug);

    $data = $cli
        ->setCurlTimeout(20) // their api may be slow
        ->setStorageCacheTime(3600) // their api may not return Cache-Control headers
        ->get('https://api.example.com/object/1234');


## Local Storage
For now, only 1 storage engine is supported, MongoDB.  
See [src/Scrape/Storage/Backend](https://github.com/mgribov/apiscrape/tree/master/src/Scrape/Storage/Backend) for details

The document structure in MongoDB is as follows:  

    {
        "_id" : ObjectId("1234567890abcdef"),
        "path" : "https://api.example.com/object/1234",
        "response" : {
            "user" : {
                "id": 1234,
                "name": "bob"
            }
        },
        "cache" : 1402931406,
        "etag" : "9331ce52904149e0325611517dfa4345"
    }

**_id** - mongo auto-generated id  

**path** - the actual URL, **there is a unique index on this field**  

**response** - the exact JSON response from the API  

**cache** - expiration timestamp in the future from Cache-Control or local configuration   
If current time is less than this value, no HTTP request will be issued, and this object will be returned directly from cache    

**etag** - optional Etag value returned by the API  