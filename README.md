# Generic JSON API scraper with local storage

## Install via Composer into existing project
    curl -sS https://getcomposer.org/installer | php # if composer is not installed
    ./composer.phar require mgribov/apiscrape

## Local Storage
For now, only 1 storage engine is supported, MongoDB.

See [src/Scrape/Storage](https://github.com/mgribov/apiscrape/tree/master/src/Scrape/Storage) for details

## Examples
See [examples/](https://github.com/mgribov/apiscrape/tree/master/examples) folder for all use cases

Make sure you install composer and run "./composer.phar dump-autoload" first to use the examples
