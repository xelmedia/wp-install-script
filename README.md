# WP Install Script

## Overview

The **WP Install Script** automates setting up a WordPress environment using **Bedrock**. It takes care of:

- **Downloading required PHAR files** like `wp-cli.phar` and `composer.phar`.
- **Validating your PHP environment** to ensure compatibility.
- **Installing Bedrock** via Composer, providing a modern WordPress project structure.
- **Running WP-CLI commands** to install WordPress core, languages, and rewrite rules.
- **Error handling and cleanup** to avoid partial installs.

Script optionally accepts, using STDIN, a GitHub access token, so required files can be downloaded from GitHub.
If no Access token is provided, requests to GitHub will be done without and might cause rate limiting issues.

## Key Components

1. **`DownloadService`**
   - Manages downloading files such as `wp-cli.phar` and `composer.phar`.
   - Ensures each file is placed in the correct directory (`WPResources`).

2. **`WPCommandService`**
   - Wraps WP-CLI commands for WordPress core installation, rewriting rules, and language setup.
   - All commands are executed in the target document root.

3. **`ComposerCommandService`**
   - Handles Composer operations.
   - In this script, it’s primarily responsible for installing Bedrock.

4. **`WpInstallHelper`**
   - Provides helper methods such as PHP version validation and response generation (e.g., success or error messages).

5. **`FileHelper`**
   - Assists with file system operations like clearing directories or removing files.

## Why Bedrock?

**Bedrock** is a modern WordPress stack that improves upon the standard WordPress installation by:

- Using Composer to manage plugins and WordPress core as dependencies.
- Adding structure for version control, making it easier to track and deploy changes.
- Offering a `.env` approach for environment-specific configurations.

This script leverages Bedrock to give you a cleaner, more maintainable WordPress project.

## Installation Flow

Below is the general installation flow as handled in the `WpInstallService` class:

1. **Validate PHP Version**  
   The script checks that you’re running a compatible PHP version (PHP 8.1+). If the environment doesn’t meet requirements, the script halts and provides an error message.

2. **Clear Existing Files**
   - The script removes old WordPress files or partial installs to prevent conflicts.
   - Protected files (like `.env` or `.env.zilch`) remain to preserve credentials or custom settings.

3. **Download WP-CLI & Composer**
   - **`downloadPharFile`**: Fetches the latest `wp-cli.phar` into `WPResources`.
   - **`downloadComposerPharFile`**: Fetches `composer.phar` similarly.

4. **Install Bedrock**
   - `composerCommandService->installBedrock()` is called to run Composer commands that fetch and set up Bedrock (e.g., `composer create-project roots/bedrock`).
   - This provides a structured WordPress project with a separate `web` directory for the WordPress core.

5. **Run WP-CLI Commands**
   - **Install WordPress Core**: `executeWpCoreInstall($domainName, $projectName)` sets up the WordPress database and required tables using Bedrock’s structure.
   - **Rewrite Rules**: `executeWpReWrite()` ensures the `.htaccess` or Nginx rewrite rules are set correctly.
   - **Language Commands**: `executeWpLanguageCommands()` installs or updates language packs as needed.

6. **Generate Success or Error Response**
   - If **all steps** are successful, you get a **200**-like response (indicating success).
   - If **any step** fails (e.g., network error, invalid config), the script calls `cleanUpScript(true)` to remove any partial or corrupted setup, then provides a **500**-like error response.

## Cleanup Process

After installation completes—whether **successfully** or with **failures**—`cleanUpScript` does the following:

- **Removes the `WPResources` directory** (where PHAR files were downloaded).
- **Deletes the running Phar file** (if not in `testing` mode).
- If there was an error, optionally **removes the entire WordPress/Bedrock installation** so you’re left with a clean slate.

## Usage

1. **Generate/Obtain the PHAR**
   - Make sure the script is packaged as a `.phar` or run directly via PHP.

2. **Run the Script**
   ```bash
   php zilch-wordpress-install-script.phar -p <projectName> -d <domainName> -e <environment>