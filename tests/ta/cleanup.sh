#!/bin/bash

find . -type f ! -name 'zilch-wordpress-install-script.phar' ! -name '.db.env' -exec rm {} +
find . -mindepth 1 -type d -empty -delete
