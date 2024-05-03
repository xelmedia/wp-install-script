#!/usr/bin/env bash

mkdir -p /var/www/html/cms/wp-content/plugins/auth0-tmp
cd /var/www/html/cms/wp-content/plugins/auth0-tmp
composer require -n symfony/http-client nyholm/psr7 auth0/wordpress:^5.0
mkdir ../auth0
mv ./vendor/auth0/wordpress/* ../auth0
cd ../auth0
composer install --no-dev
rm -rf /var/www/html/cms/wp-content/plugins/auth0-tmp