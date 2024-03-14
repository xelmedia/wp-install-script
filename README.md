# WP Install Script

This project is responsible for installing WordPress on client sites.

The expectation is that the `WPInstallScript.php` and the `.htaccess` file should be downloaded to the document root and executed.

## WPInstallScript

### Script steps:

1) The script will download a Phar file that helps execute different WordPress commands such as `wp core download`.
2) The script will execute `wp core download` to download the WordPress files.
3) The `wp config create` command will be executed to create a `wp-config.php` file inside the WordPress files to establish a database connection. This step will fail if the database is unreachable.
4) WordPress installation & language installation will be executed.
5) Different plugins will be installed, e.g., `wp-graphql`, `zilch assistant plugin`.
6) If all the previous steps have succeeded, a 200 response will be returned.

### Steps failure:

If any of the script's steps fail, the following steps will be executed:

1) The WordPress directory will be deleted.
2) The db env file will be deleted.
3) The resources directory (where the Phar file is located) will be deleted.
4) The script itself will be deleted.
5) A 500 response including the error cause will be returned.

### Call the script

Basically it's just a php script, so you can call it by doing the next :
php zilch-wordpress-install-script.php -d [domainName] -p [projectname] 
