#!/usr/bin/env bash
LOCAL=false

if [ "$1" == "true" ] || [ "$1" == "false" ]; then
    LOCAL=$1
fi
yarn add @wordpress/scripts @wordpress/env copy-webpack-plugin
node_modules/.bin/wp-env stop || :
node_modules/.bin/wp-env clean
node_modules/.bin/wp-env start --update

# update php.ini
CONTAINER_ID=$(docker ps --filter "name=tests-cli" -q)
docker exec --user root $CONTAINER_ID bash -c "echo 'phar.readonly = off' >> /usr/local/etc/php/php.ini"
docker restart $CONTAINER_ID
sleep 10
# Copy the PHAR file to the WordPress environment
pharFile="../../zilch-wordpress-install-script.phar"
NEW_CONTAINER_ID=$(docker ps --filter "name=tests-cli" -q)
cd ../../ || exit 1
if [ "$LOCAL" == "true" ]; then
    composer install -n
else
    wget -q https://getcomposer.org/composer.phar
    php composer.phar install -n
    rm composer.phar
fi
./vendor/bin/box compile
cd - || exit 1
docker cp "$pharFile" $NEW_CONTAINER_ID:/var/www/html/zilch-wordpress-install-script.phar

# Get the htaccess file and other necessary files
htaccessContent=$(<../../.htaccess)
auth0EnvContent=$(<./.auth0.env)

# Use wp-env run tests-cli to copy the contents to the container
echo "$htaccessContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.htaccess > /dev/null
echo "$auth0EnvContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.auth0.env > /dev/null

# Get db config from the docker container using these commands
db_name=$(node_modules/.bin/wp-env run tests-cli wp config get DB_NAME)
db_user=$(node_modules/.bin/wp-env run tests-cli wp config get DB_USER)
db_password=$(node_modules/.bin/wp-env run tests-cli wp config get DB_PASSWORD)
db_host=$(node_modules/.bin/wp-env run tests-cli wp config get DB_HOST)

# Create a .db.env file and copy the vars to there
echo "DB_NAME=$db_name" > .db.env
echo "DB_USER=$db_user" >> .db.env
echo "DB_PASS=$db_password" >> .db.env
echo "DB_HOST=$db_host" >> .db.env

cat .db.env | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.db.env > /dev/null

# Copy and set permissions for the cleanup script
cleanupContent=$(<./cleanup.sh)
echo "$cleanupContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/cleanup.sh > /dev/null
node_modules/.bin/wp-env run tests-cli chmod +x /var/www/html/cleanup.sh

# Run the cleanup script and the PHAR file
node_modules/.bin/wp-env run tests-cli /var/www/html/cleanup.sh
node_modules/.bin/wp-env run tests-cli php /var/www/html/zilch-wordpress-install-script.phar -p p -i id -d d

# Run the test script
php test-zilch-wordpress-install-script.php
