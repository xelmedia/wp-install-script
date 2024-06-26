# WP Install Script

This project automates the process of installing WordPress on client sites. It's designed to streamline the setup by handling WordPress core download, configuration, plugin installation, and more through an efficient, automated script.

The expectation is that the `zilch-wordpress-install-script.php` and the `.htaccess` file should be downloaded to the document root and executed.

## TODO

It would be nicer to split up the implementation in a project-like structure, with a "/src", classes and separated implementations.
However, this script (`zilch-wordpress-install-script.php`) is a single-php script file that is downloaded by WPCLI and executed.

A solution would be to create a `.phar` executable.

## WPInstallScript

### Script steps:

1) The script will download a Phar file that helps execute different WordPress commands such as `wp core download`.
2) The script will execute `wp core download` to download the WordPress files.
3) The `wp config create` command will be executed to create a `wp-config.php` file inside the WordPress files to establish a database connection. This step will fail if the database is unreachable.
4) WordPress' installation & language installation will be executed.
5) Different plugins will be installed, e.g., `wp-graphql`, `zilch assistant plugin`.
6) If all the previous steps have succeeded, a 200 response will be returned.
7) After succession some files and folders will be deleted.

### Steps failure:

If any of the script's steps fail, the following steps will be executed:

1) The WordPress directory will be deleted.
2) The `.db.env` file will be deleted.
3) The resources directory (where the Phar file is located) will be deleted.
4) The script itself will be deleted.
5) A 500 response including the error cause will be returned.

### Script Requirements:
1) PHP version >= 8.1
2) `.db.env` file that contains the necessary data to create a database connection
3) The format of the env file should be as following:
```
DB_HOST=
DB_NAME=
DB_PASS=
DB_USER=
```
4) The .htaccess file must be in place

### Execute the script
To execute the script, use the following command in your terminal:

`php zilch-wordpress-install-script.php -d [domainName] -i [projectId] -p [projectName]`

Replace [domainName] and [projectName] with your actual domain name and project name.
