#!/bin/bash

find . -type f ! -name 'WPInstallScript.php' ! -name '.db.env' ! -name '.htaccess' -exec rm {} +
find . -mindepth 1 -type d -empty -delete
