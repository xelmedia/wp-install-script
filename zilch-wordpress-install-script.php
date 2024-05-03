<?php

class ScriptHelper {
    private string $wordpressPath;
    private string $dbEnvFilePath;
    private string $auth0EnvFilePath;
    private string $auth0ScriptPath;
    private string $runLevel;

    private const wordpressVersion = '6.5.2';
    private const graphqlGutenbergPluginVersion = "0.4.1";
    private const graphqlPluginVersion = "1.22.0";
    private const contactForm7Version = "5.9";
    private const pharFileDirectory = __DIR__ . "/WPResources";
    private const pharFilePath = self::pharFileDirectory . "/wp-cli.phar";
    private const phpBin = PHP_BINARY;

    public function __construct($wordpressPath, $envFilePath, $auth0EnvFilePath, $auth0ScriptPath, $environment)
    {
        $this->wordpressPath = $wordpressPath;
        $this->dbEnvFilePath = $envFilePath;
        $this->auth0EnvFilePath = $auth0EnvFilePath;
        $this->auth0ScriptPath = $auth0ScriptPath;
        $this->runLevel = $environment;
    }

    /**
     * downloads a wp-cli.phar files that will help executing wordpress commands
     * @return void
     * @throws Exception
     */
    private function downloadPharFile(): void
    {
        try {
            $downloadUrl = "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar";
            // If directory doesnt exists, create one!
            if (!file_exists(self::pharFileDirectory)) {
                mkdir(self::pharFileDirectory, 0777, true);
            }
            if (file_put_contents(self::pharFilePath, file_get_contents($downloadUrl)) !== false) {
                chmod(self::pharFilePath, 0755);
            }
        } catch (Error|Exception|Throwable $e) {
            throw new Exception("Something went wrong while downloading wp phar file", 500, $e->getMessage());
        }
    }

    /**
     * @param string $command
     * @param string $errorMessage
     * @param int $errorCode
     * @return void
     * @throws Exception
     */
    private function executeWPCommand(string $command, string $errorMessage = "", int $errorCode = 500): void {
        $wpCommand = self::phpBin . " " . self::pharFilePath . " " . $command . " --path=$this->wordpressPath";
        if(!exec($wpCommand)) {
            throw new Exception(!empty($errorMessage) ? $errorMessage : "Something went wrong executing the command: $wpCommand", $errorCode);
        }
    }

    /**
     * Checks if the wordpress dir exists
     * @return bool
     */
    private function wordpressDirExists(): bool {
        return file_exists($this->wordpressPath);
    }

    /**
     * Removes a file given path
     * No errors will be thrown if the file doesnt exist
     * @param $path
     * @return void
     */
    private function removeFile($path): void {
        if(file_exists($path)) {
            unlink($path);
            return;
        }
        echo "File at the path: $path doesnt exist";
    }

    /**
     * removes a directory given the path
     * No errors will be thrown if the directory doesnt exist
     */
    private function removeDir($dirPath): void {
        if (file_exists($dirPath)) {
            $it = new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it,
                RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($dirPath);
        }
    }

    /**
     * Executes a command to download the wp core files
     * Throws an error if the wordpress directory doesnt exists at defined the wordpress path
     * @throws Exception
     */
    private function executeCoreDownload(): void {
        if (!exec(self::phpBin . " "  . self::pharFilePath . ' core download --version=' . escapeshellarg(self::wordpressVersion) . ' --path=' . $this->wordpressPath)) {
            throw new Exception("The wordpress core was not downloaded successfully", 500);
        }
        if (!$this->wordpressDirExists()) {
            throw new Exception("The wordpress core was not downloaded successfully", 500);
        }
    }

    /**
     * reads the env file and puts all the vars in an array as $key => $value
     * @return array
     */
    private function readEnvFile($path): array {
        $envData = [];
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                list($key, $value) = explode('=', $line, 2);
                $envData[trim($key)] = trim($value);
            }
        }
        return $envData;
    }

    /**
     * Reads the env file vars and uses it to execute a config create command at the wordpress directory
     * @return void
     * @throws Exception
     */
    private function executeCreateWpConfig(): void {
        $envData = $this->readEnvFile($this->dbEnvFilePath);
        $command = 'config create --dbname=' . escapeshellarg($envData["DB_NAME"]) . ' --dbuser=' . escapeshellarg($envData["DB_USER"]) . ' --dbpass=' . escapeshellarg($envData["DB_PASS"]) . ' --dbhost=' . escapeshellarg($envData["DB_HOST"] ?? "localhost");
        $this->executeWPCommand($command, "Something went wrong while creating wordpress database config");
    }

    /**
     * @throws Exception
     */
    private function configureAuth0(): void {
        exec("chmod +x $this->auth0ScriptPath && sh $this->auth0ScriptPath");
        $this->executeWPCommand("plugin activate auth0");
        $this->validatePluginIsInstalled("auth0");
        $auth0Array = $this->readEnvFile($this->auth0EnvFilePath);
        $options = array(
            'auth0_state' => array(
                'enable' => "true"
            ),
            'auth0_accounts' => array(
                'matching' => 'strict',
                'missing' => 'create',
                'default_role' => 'administrator',
                'passwordless' => "true"
            ),
            'auth0_client' => array(
                'id' => $auth0Array["ZILCH_AUTH0_CLIENT_ID"],
                'secret' => base64_decode($auth0Array["ZILCH_AUTH0_CLIENT_SECRET"]),
                'domain' => $auth0Array["ZILCH_AUTH0_TENANT_DOMAIN"]
            ),
            'auth0_client_advanced' => array(
                'custom_domain' => $auth0Array["ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN"]
            ),
            'auth0_tokens' => array(
                'caching' => 'wp_object_cache'
            ),
            'auth0_sessions' => array(
                'method' => 'cookies',
                'session_ttl' => 0,
                'rolling_sessions' => "true",
                'refresh_tokens' => "false"
            )
        );
        foreach($options as $option_name => $option_value) {
            $json_value = json_encode($option_value);
            // Use escapeshellarg to escape the JSON string for use in the command
            $escaped_value = escapeshellarg($json_value);
            $command = "option update $option_name $escaped_value --format=json --autoload=yes";
            $this->executeWPCommand($command, "something went wrong adding the option $option_name");
        }
    }

    /**
     * It Add/Update the zilch options to the wp options db table
     * @throws Exception
     */
    private function addZilchOptions(): void {
        $authOptions = $this->readEnvFile($this->auth0EnvFilePath);
        $zilchClient = $authOptions["ZILCH_AUTH0_CLIENT_SECRET"];
        $gatewayHost = $authOptions["ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN"];
        $commands = [
            "option update zilch_client_secret " . escapeshellarg($zilchClient),
            "option update zilch_gateway_host ". escapeshellarg($gatewayHost)
        ];
        foreach ($commands as $command) {
            $this->executeWPCommand($command, "Something went wrong while adding zilch options");
        }
    }

    /**
     * it creates a mocked password and email
     * and executes wp core install command given the domain name and project name
     * @param $domainName
     * @param $projectName
     * @return void
     * @throws Exception
     */
    private function executeWpCoreInstall($domainName, $projectName): void {
        $adminEmail = "email@zilch.nl";
        $command = 'core install --url=' . escapeshellarg($domainName) . ' --title=' . escapeshellarg($projectName) . ' --admin_user=zilch-admin ' . '--admin_email=' . escapeshellarg($adminEmail);
        $this->executeWPCommand($command, "Something went wrong while installing wordpress core for the given domain name: $domainName");
    }

    /**
     * installs and activate dutch language to the wp core files
     * @throws Exception
     */
    private function executeWpLanguageCommands(): void {
        $command = 'language core install nl_NL --activate';
        $this->executeWPCommand($command, "Something went wrong while installing and updating the language");
    }

    /**
     * Execute an install plugin command on the wordpress directory
     * @param String $plugin
     * @throws Exception
     */
    private function installPlugin(String $plugin): void {
        $command = 'plugin install ' . escapeshellarg($plugin) . ' --activate';
        $this->executeWPCommand($command, "Something went wrong while installing the plugin: $plugin");
    }

    /**
     * validate that plugin is installed given plugin name
     * it will check if the given plugin exists in the plugins folder
     * throws an error if the plugin couldn't be found
     * @throws Exception
     */
    private function validatePluginIsInstalled(string $pluginName): void {
        if(!is_dir("$this->wordpressPath/wp-content/plugins/$pluginName")) {
            throw new Exception("The plugin $pluginName was not installed correctly", 500);
        }
    }

    /**
     * It will install and activate plugins
     * It will throw an error if a plugin was not installed successfully
     * @throws Exception
     */
    private function installPlugins(): void {
        $plugins = [
            'wp-graphql' => "https://downloads.wordpress.org/plugin/wp-graphql.". self::graphqlPluginVersion .".zip",
            'wp-graphql-gutenberg' => "https://github.com/pristas-peter/wp-graphql-gutenberg/archive/refs/tags/v".self::graphqlGutenbergPluginVersion.".zip",
            'contact-form-7' => "https://downloads.wordpress.org/plugin/contact-form-7.". self::contactForm7Version .".zip",
            'zilch-assistant' => 'https://gitlab.xel.nl/albert/kameleon-assistant-plugin-zip/-/raw/latest/zilch-assistant.zip'
        ];
        foreach ($plugins as $pluginName => $pluginSlug) {
            $this->installPlugin($pluginSlug);
            $this->validatePluginIsInstalled($pluginName);
        }
    }

    /**
     * it will generate a response for the script
     * returns 200 if $error is null otherwise returns 500 with the given error
     * @param Error|Exception|Throwable|null $error
     * @return void
     */
    private function generateResponse(Error|Exception|Throwable $error = null): void {
        $data = ["responseCode" => 200];
        if ($error) {
            $data = [
                "responseCode" => 500,
                "error" => [
                    "message" => $error->getMessage(),
                    "code" => $error->getCode()
                ]
            ];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data) . "\n";
        if($error) {
            exit($error->getCode());
        }
    }

    private function cleanUpScript($removeWordPress = false): void {
        $this->removeFile($this->dbEnvFilePath);
        $this->removeDir(__DIR__."/WPResources");
        unlink(__FILE__);
        unlink("$this->wordpressPath/wp-cli.yml");
        unlink($this->auth0ScriptPath);
        unlink($this->auth0EnvFilePath);
        if($removeWordPress) {
            $this->removeDir($this->wordpressPath);
            $this->removeFile(__DIR__."/.htaccess");
        }
    }

    /**
     * @throws Exception
     */
    private function validatePHPVersion(): void {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            throw new Exception('PHP version 8.1 or higher is required.');
        }
    }

    private function executeWpReWrite(): void {
       if(!exec("cd $this->wordpressPath && " . self::phpBin . " " . self::pharFilePath  . " rewrite structure '/%postname%/' --hard  --path=$this->wordpressPath")) {
           throw new Exception("Something went wrong while executing wp rewrite", 500);
       }
    }

    private function generateYMLFile(): void {
        $content = <<<YAML
apache_modules:
    - mod_rewrite

YAML;
        file_put_contents($this->wordpressPath."/wp-cli.yml", $content);
    }

    private function deployManifest(): void {
        $auth0Array = $this->readEnvFile($this->auth0EnvFilePath);
        $zilchClient = $auth0Array["ZILCH_AUTH0_CLIENT_SECRET"];
        $gatewayHost = $auth0Array["ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN"];
        $postUrl = "https://" . $gatewayHost . "/v1/deploy/manifest";
        $mockedData = json_encode([
            'key' => 'value'
        ]);

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                    "X-Zilch-Client-Secret: $zilchClient\r\n",
                'method' => 'POST',
                'content' => $mockedData
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($postUrl, false, $context);
        if($result === false) {
            throw new Exception("Error making request to $postUrl", 500);
        }
    }

    /**
     * it will executes couple of commands to ensure that the wordpress
     * is downloaded and installed correctly (including installing plugins, language and defining database config)
     * @sucess : it will returns a 200 response
     * @failure: it will returns 500 response including th error and it will remove the
     * wordpress folder, env file, WPResources directory and the script itself
     * @param $domainName
     * @param $projectName
     * @return void
     */
    public function installWpScripts($domainName, $projectName): void {
        try {
            $this->validatePHPVersion();
            if($this->wordpressDirExists()) {
                $error = new Error("The cms directory already exists", 400);
                $this->generateResponse($error);
            }
            $this->downloadPharFile();
            $this->executeCoreDownload();
            $this->executeCreateWpConfig();
            $this->executeWpCoreInstall($domainName, $projectName);
            $this->executeWpLanguageCommands();
            $this->installPlugins();
            $this->configureAuth0();
            $this->addZilchOptions();
            $this->generateYMLFile();
            $this->executeWpRewrite();
            $this->deployManifest();
            $this->generateResponse();
            $this->cleanUpScript();
        } catch (Error|Exception|Throwable $e) {
            $this->cleanUpScript(true);
            $this->generateResponse($e);
        }
    }
}


function getOptions() {
    // Get options from the command line
    return getopt("p:d:e:", ["projectName:", "domainName:", "environment:"]);
}

// get the options of the command
$options = getOptions();
// get the project name and domain name from the short/long options
$projectName = $options['p'] ?? $options['projectName'] ?? null;
$domainName = $options['d'] ?? $options['domainName'] ?? null;
$environment = $options['e'] ?? $options['environment'] ?? "development";
if(!$domainName || !$projectName) {
    echo "Usage: php zilch-wordpress-install-script.php -p <projectName> -d <domainName> OR php zilch-wordpress-install-script.php --projectName=<projectName> --domainName=<domainName>";
    exit(1);
}
$helper = new ScriptHelper(__DIR__."/cms", __DIR__."/.db.env", __DIR__ . "/.auth0.env", __DIR__."/auth0-install.sh", $environment);
$helper->installWpScripts($domainName, $projectName);
