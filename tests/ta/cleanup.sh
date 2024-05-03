#!/bin/bash

find . -type f ! -name 'zilch-wordpress-install-script.php' ! -name '.db.env' ! -name '.htaccess' ! -name '.auth0.env' ! -name 'auth0-install.sh' -exec rm {} +
find . -mindepth 1 -type d -empty -delete
