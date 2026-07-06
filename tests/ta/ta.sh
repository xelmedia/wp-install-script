#!/usr/bin/env bash
set -euo pipefail

# Copy the PHAR file to the WordPress environment
pharFile="../../zilch-wordpress-install-script.phar"

if [ ! -f "$pharFile" ]; then
    echo -e "\033[31mError: $pharFile does not exist, generate first using: $ composer build.\033[0m"
    exit 1
fi

# Empty backup-* dir (required by phar; wp-util creates this before first install).
ta_create_empty_backup() {
    node_modules/.bin/wp-env run tests-cli sh -c 'B=/var/www/html/backup-ta-$(date +%s); mkdir -p "$B"; echo "$B"'
}

# Snapshot docroot into backup-{timestamp}/ like wp-util ZilchScriptHelper::createBackupFolder.
ta_snapshot_docroot_backup() {
    node_modules/.bin/wp-env run tests-cli sh -c '
        DOCROOT=/var/www/html
        B="$DOCROOT/backup-ta-$(date +%s)"
        mkdir -p "$B"
        find "$DOCROOT" -maxdepth 1 -mindepth 1 \
            ! -name "backup-*" \
            ! -name "zilch-wordpress-install-script.phar" \
            ! -name ".env" \
            ! -name ".env.zilch" \
            -exec mv {} "$B/" \;
        echo "$B"
    '
}

run_phar() {
    local backup_path="$1"
    shift
    echo "" | node_modules/.bin/wp-env run tests-cli php /var/www/html/zilch-wordpress-install-script.phar \
        -p project.com \
        -d domain.com \
        --admin-email="email@zilch.website" \
        --static-content-dirs="" \
        --backup-folder-path="${backup_path}" \
        "$@"
}

yarn install --frozen-lockfile
node_modules/.bin/wp-env stop 2>/dev/null || true
node_modules/.bin/wp-env destroy 2>/dev/null || true
node_modules/.bin/wp-env start --update

NEW_CONTAINER_ID=$(docker ps --filter "name=tests-cli" -q)
cd ../../ || exit 1
cd - || exit 1

db_name=$(node_modules/.bin/wp-env run tests-cli wp config get DB_NAME)
db_user=$(node_modules/.bin/wp-env run tests-cli wp config get DB_USER)
db_password=$(node_modules/.bin/wp-env run tests-cli wp config get DB_PASSWORD)
db_host=$(node_modules/.bin/wp-env run tests-cli wp config get DB_HOST)
wp_home=$(node_modules/.bin/wp-env run tests-cli wp option get siteurl)

cleanupContent=$(<./cleanup.sh)
echo "$cleanupContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/cleanup.sh > /dev/null
node_modules/.bin/wp-env run tests-cli chmod +x /var/www/html/cleanup.sh

echo "DB_NAME=$db_name" > .env
echo "DB_USER=$db_user" >> .env
echo "DB_PASSWORD=$db_password" >> .env
echo "DB_HOST=$db_host" >> .env
echo "DB_PREFIX=zilch_" >> .env
echo "WP_HOME='${wp_home}'" >> .env
echo 'WP_SITEURL="${WP_HOME}/wp"' >> .env
echo 'WP_ENV="test"' >> .env

node_modules/.bin/wp-env run tests-cli wp db reset --yes
node_modules/.bin/wp-env run tests-cli /var/www/html/cleanup.sh

zilchEnvContent=$(<./.env.zilch)
echo "$zilchEnvContent" | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.env.zilch > /dev/null

cat .env | node_modules/.bin/wp-env run tests-cli tee /var/www/html/.env > /dev/null
rm .env

docker cp "$pharFile" "$NEW_CONTAINER_ID:/var/www/html/zilch-wordpress-install-script.phar"
INSTALL_BACKUP=$(ta_create_empty_backup)
run_phar "$INSTALL_BACKUP"
node_modules/.bin/wp-env run tests-cli rm -rf "$INSTALL_BACKUP"
php test-zilch-wordpress-install-script.php

docker cp "$pharFile" "$NEW_CONTAINER_ID:/var/www/html/zilch-wordpress-install-script.phar"
php test-zilch-wordpress-install-script.php prepare-update
UPDATE_BACKUP=$(ta_snapshot_docroot_backup)
run_phar "$UPDATE_BACKUP" --update=true
node_modules/.bin/wp-env run tests-cli rm -rf "$UPDATE_BACKUP"

node_modules/.bin/wp-env run tests-cli sh -c 'rm -rf /var/www/html/backup-* 2>/dev/null || true'
php test-zilch-wordpress-install-script.php update

docker cp "$pharFile" "$NEW_CONTAINER_ID:/var/www/html/zilch-wordpress-install-script.phar"
RETRY_BACKUP=$(ta_snapshot_docroot_backup)
run_phar "$RETRY_BACKUP"
php test-zilch-wordpress-install-script.php
