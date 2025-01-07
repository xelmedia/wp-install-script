#!/usr/bin/env bash

# Copy the PHAR file to the WordPress environment
pharFile="../../zilch-wordpress-install-script.phar"

if [ ! -f "$pharFile" ]; then
    # Print error message in red and exit
    echo -e "\033[31mError: $pharFile does not exist, generate first using: $ composer build.\033[0m"
    exit 1
fi

yarn add @wordpress/scripts @wordpress/env copy-webpack-plugin
node_modules/.bin/wp-env stop || :
node_modules/.bin/wp-env clean
node_modules/.bin/wp-env start --update

NEW_CONTAINER_ID=$(docker ps --filter "name=tests-cli" -q)
cd ../../ || exit 1
cd - || exit 1

# Get the htaccess file and other necessary files
htaccessContent=$(<../../.htaccess)
auth0EnvContent=$(<./.auth0.env)

# Use wp-env run tests-cli to copy the contents to the container
echo "$htaccessContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.htaccess > /dev/null
echo "$auth0EnvContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.env.zilch > /dev/null

# Get db config from the docker container using these commands
db_name=$(node_modules/.bin/wp-env run tests-cli wp config get DB_NAME)
db_user=$(node_modules/.bin/wp-env run tests-cli wp config get DB_USER)
db_password=$(node_modules/.bin/wp-env run tests-cli wp config get DB_PASSWORD)
db_host=$(node_modules/.bin/wp-env run tests-cli wp config get DB_HOST)

# Copy and set permissions for the cleanup script
cleanupContent=$(<./cleanup.sh)
echo "$cleanupContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/cleanup.sh > /dev/null
node_modules/.bin/wp-env run tests-cli chmod +x /var/www/html/cleanup.sh

# Run the cleanup script and the PHAR file
node_modules/.bin/wp-env run tests-cli /var/www/html/cleanup.sh


# Create a .db.env file and copy the vars to there
echo "DB_NAME=$db_name" > .env
echo "DB_USER=$db_user" >> .env
echo "DB_PASS=$db_password" >> .env
echo "DB_HOST=$db_host" >> .env

cat .env | node_modules/.bin/wp-env run tests-cli tee /var/www/html/cms/.env > /dev/null

node_modules/.bin/wp-env run tests-cli mkdir /var/www/html/preview-site
node_modules/.bin/wp-env run tests-cli mkdir /var/www/html/live-site
node_modules/.bin/wp-env run tests-cli mkdir /var/www/html/cms/
docker cp "$pharFile" $NEW_CONTAINER_ID:/var/www/html/cms/zilch-wordpress-install-script.phar

node_modules/.bin/wp-env run tests-cli php /var/www/html/zilch-wordpress-install-script.phar -p p -d d --static-content-dirs='/var/www/html/preview-site,/var/www/html/live-site'

# Run the test script
php test-zilch-wordpress-install-script.php
