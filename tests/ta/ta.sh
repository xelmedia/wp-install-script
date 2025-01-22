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
# Get db config from the docker container using these commands
db_name=$(node_modules/.bin/wp-env run tests-cli wp config get DB_NAME)
db_user=$(node_modules/.bin/wp-env run tests-cli wp config get DB_USER)
db_password=$(node_modules/.bin/wp-env run tests-cli wp config get DB_PASSWORD)
db_host=$(node_modules/.bin/wp-env run tests-cli wp config get DB_HOST)
wp_home=$(node_modules/.bin/wp-env run tests-cli wp option get siteurl)

cleanupContent=$(<./cleanup.sh)
echo "$cleanupContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/cleanup.sh > /dev/null
node_modules/.bin/wp-env run tests-cli chmod +x /var/www/html/cleanup.sh

# Create a .db.env file and copy the vars to there
echo "DB_NAME=$db_name" > .env
echo "DB_USER=$db_user" >> .env
echo "DB_PASSWORD=$db_password" >> .env
echo "DB_HOST=$db_host" >> .env
echo "DB_PREFIX=zilch" >> .env
echo "WP_HOME='${wp_home}'" >> .env
echo 'WP_SITEURL="${WP_HOME}/wp"' >> .env
echo 'WP_ENV="test"' >> .env

# Run the cleanup script and the PHAR file
node_modules/.bin/wp-env run tests-cli /var/www/html/cleanup.sh
# Use wp-env run tests-cli to copy the contents to the container

zilchEnvContent=$(<./.env.zilch)
echo "$zilchEnvContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.env.zilch > /dev/null

cat .env | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.env > /dev/null
rm .env

# Copy the script and execute the install and verify (using test script) the result
docker cp "$pharFile" $NEW_CONTAINER_ID:/var/www/html/zilch-wordpress-install-script.phar
node_modules/.bin/wp-env run tests-cli php /var/www/html/zilch-wordpress-install-script.phar -p project.com -d domain.com --admin-email="email@zilch.website" --static-content-dirs=""
php test-zilch-wordpress-install-script.php

# RETRY to make sure subsequent installations will succeed
docker cp "$pharFile" $NEW_CONTAINER_ID:/var/www/html/zilch-wordpress-install-script.phar
node_modules/.bin/wp-env run tests-cli php /var/www/html/zilch-wordpress-install-script.phar -p project.com -d domain.com --admin-email="email@zilch.website" --static-content-dirs=""
php test-zilch-wordpress-install-script.php