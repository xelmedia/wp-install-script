#!/usr/bin/env bash

yarn global add @wordpress/scripts @wordpress/env copy-webpack-plugin
node_modules/.bin/wp-env stop && node_modules/.bin/wp-env start &&
## Copy script from root dir to the test dir
cp ../../WPInstallScript.php ./
cp ../../.htaccess ./

# Put the script content in a variable
scriptContent=$(<WPInstallScript.php)
htaccessContent=$(<.htaccess)
# Use wp-env run tests-cli to execute a command inside the container to copy the content to another file
echo "$scriptContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/WPInstallScript.php > /dev/null
echo "$htaccessContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.htaccess > /dev/null

# Get db config from the docker container using theses commands
db_name=$(wp-env run tests-cli wp config get DB_NAME)
db_user=$(wp-env run tests-cli wp config get DB_USER)
db_password=$(wp-env run tests-cli wp config get DB_PASSWORD)
db_host=$(wp-env run tests-cli wp config get DB_HOST)

# Create a .db.env file and copy the vars to there
echo "DB_NAME=$db_name" > .db.env
echo "DB_USER=$db_user" >> .db.env
echo "DB_PASS=$db_password" >> .db.env
echo "DB_HOST=$db_host" >> .db.env

cat .db.env | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.db.env > /dev/null

cleanupContent=$(<cleanup.sh)
echo "$cleanupContent" | wp-env run tests-cli tee /var/www/html/cleanup.sh > /dev/null
node_modules/.bin/wp-env run tests-cli chmod +x cleanup.sh
node_modules/.bin/wp-env run tests-cli ./cleanup.sh
node_modules/.bin/wp-env run tests-cli php WPInstallScript.php -p p -d d
php test.php
