# WP Install Script

This project automates the process of installing WordPress on client sites. It's designed to streamline the setup by handling WordPress core download, configuration, plugin installation, and more through an efficient, automated script.
The expectation is that the `zilch-wordpress-install-script.phar` will be generated using the `box` library. It gathers the classes inside the source folder and will make it one executable file.

## WPInstallScript

### Script steps:

1. The script will download a Phar file that helps execute different WordPress commands such as `wp core download`. 
2. The script will execute `wp core download` to download the WordPress files. 
3. The `wp config create` command will be executed to create a `wp-config.php` file inside the WordPress files to establish a database connection. This step will fail if the database is unreachable. 
4. WordPress' installation & language installation will be executed. 
5. Different plugins will be installed, e.g., `wp-graphql`, `zilch assistant plugin`. 
6. If all the previous steps have succeeded, a 200 response will be returned. 
7. After succession some files and folders will be deleted.

### Steps failure:

If any of the script's steps fail, the following steps will be executed:

1. The WordPress directory will be deleted. 
2. The `.db.env` file will be deleted. 
3. The resources directory (where the Phar file is located) will be deleted. 
4. The script itself will be deleted. 
5. A 500 response including the error cause will be returned.

### Script Requirements:
1. PHP version >= 8.1
2. `.db.env` file that contains the necessary data to create a database connection 
3. The format of the env file should be as following:
```
DB_HOST=
DB_NAME=
DB_PASS=
DB_USER=
```

### Execute the Script
To execute the script, use the following commands in your terminal:
1. Compile/build the `src` into a PHAR file using:
   `./vendor/bin/box (compile/build)`
2. Then execute the PHAR file like this:
   `php zilch-wordpress-install-script.phar -d [domainName] -i [projectId] -p [projectName]`

Replace `[domainName]`, `[projectId]`, and `[projectName]` with your actual domain name, project ID, and project name.

### Test Automation
For the test automation, we use `wp-env` to start a Docker container with WordPress already installed on it. After starting the container, follow these steps:
1. Change the `php.ini` of the container so the executable PHAR file can write on the container.
2. Compile a new PHAR file using Box.
3. Move the PHAR file into the container.
4. Create the needed files (`db.env`).
5. Execute a cleanup script to remove the installed WordPress.
6. Execute the PHAR file.
7. Perform assertions to check the installation.

### Plugins
To ensure that WordPress is installed correctly, we will install the `Zilch Assistant plugin`
