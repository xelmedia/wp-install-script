#!/usr/bin/env bash

composer_url="https://getcomposer.org/composer.phar"

script_dir="$1"

mkdir -p "$script_dir/cms/wp-content/plugins/auth0-tmp"
cd "$script_dir/cms/wp-content/plugins/auth0-tmp" || exit
curl -o composer.phar "$composer_url"
chmod +x composer.phar
php8.1 composer.phar require -n symfony/http-client nyholm/psr7 auth0/wordpress:^5.0
mkdir ../auth0
mv ./vendor/auth0/wordpress/* ../auth0
cd ../auth0 || exit
php8.1 ../auth0-tmp/composer.phar install --no-dev
rm -rf "$script_dir"/cms/wp-content/plugins/auth0-tmp/