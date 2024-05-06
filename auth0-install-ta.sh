#!/usr/bin/env bash

script_dir="$1"

mkdir -p "$script_dir/cms/wp-content/plugins/auth0-tmp"
cd "$script_dir/cms/wp-content/plugins/auth0-tmp" || exit
composer require -n symfony/http-client nyholm/psr7 auth0/wordpress:^5.0
mkdir ../auth0
mv ./vendor/auth0/wordpress/* ../auth0
cd ../auth0 || exit
composer install --no-dev
rm -rf "$script_dir"/cms/wp-content/plugins/auth0-tmp/