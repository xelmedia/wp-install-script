#!/usr/bin/env bash

composer_url="https://getcomposer.org/composer.phar"

mkdir -p cms/wp-content/plugins/auth0-tmp
cd cms/wp-content/plugins/auth0-tmp
curl -o composer.phar "$composer_url"
chmod +x composer.phar
php8.1 composer.phar require -n symfony/http-client nyholm/psr7 auth0/wordpress:^5.0
mkdir ../auth0
mv ./vendor/auth0/wordpress/* ../auth0
cd ../auth0
php8.1 ../auth0-tmp/composer.phar install --no-dev
cd ..
rm -rf auth0-tmp/