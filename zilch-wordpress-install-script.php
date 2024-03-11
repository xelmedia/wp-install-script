<?php

class ScriptHelper {
    private string $wordpressPath;
    private string $envFilePath;

    private string $runlevel;

    private const wordpressVersion = '6.4.2';
    private const graphqlGutenbergPluginVersion = "0.4.1";
    private const graphqlPluginVersion = "1.22.0";
    private const contactForm7Version = "5.9";
    private const pharFileDirectory = __DIR__ . "/WPResources";
    private const pharFilePath = self::pharFileDirectory . "/wp-cli.phar";

    public function __construct($wordpressPath, $envFilePath, $runlevel)
    {
        $this->wordpressPath = $wordpressPath;
        $this->envFilePath = $envFilePath;
        $this->runlevel = $runlevel;
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
        if (!exec('php ' . self::pharFilePath . ' core download --version=' . escapeshellarg(self::wordpressVersion) . ' --path=' . $this->wordpressPath)) {
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
    private function readEnvFile(): array {
        $envData = [];
        if (file_exists($this->envFilePath)) {
            $lines = file($this->envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
        $envData = $this->readEnvFile();
        if(!exec('php ' . self::pharFilePath .' config create --dbname=' . escapeshellarg($envData["DB_NAME"]) . ' --dbuser=' . escapeshellarg($envData["DB_USER"]) . ' --dbpass=' . escapeshellarg($envData["DB_PASS"]) . ' --dbhost='. escapeshellarg($envData["DB_HOST"] ?? "localhost") .' --path=' . $this->wordpressPath)) {
            throw new Exception("Something went wrong while creating wordpress database config", 500);
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
        if(!exec('php ' . self::pharFilePath . ' core install --url=' . escapeshellarg($domainName) . ' --title=' . escapeshellarg($projectName) . ' --admin_user=zilch-admin ' . '--admin_email=' . escapeshellarg($adminEmail) . ' --path=' . $this->wordpressPath)) {
            throw new Exception("Something went wrong while installing wordpress core for the given domain name: $domainName", 500);
        }
    }

    /**
     * installs and activate dutch language to the wp core files
     * @throws Exception
     */
    private function executeWpLanguageCommands(): void {
        try {
            if(!exec('php ' . self::pharFilePath . ' --path=' . escapeshellarg($this->wordpressPath) . ' language core install nl_NL --activate')) {
                throw new Exception("Error executing language core install", 500);
            }
        } catch (\Throwable|Exception|Error $t) {
            throw new Exception("Something went wrong while installing and updating the language", 500, $t->getPrevious());
        }
    }

    /**
     * Execute an install plugin command on the wordpress directory
     * @param String $plugin
     * @throws Exception
     */
    private function installPlugin(String $plugin): void {
        if(!exec('php ' . self::pharFilePath . ' plugin install ' . escapeshellarg($plugin) . ' --activate --path=' . escapeshellarg($this->wordpressPath))) {
            throw new Exception("Something went wrong while installing the plugin: $plugin", 500);
        }
    }

    /**
     * validate that plugin is installed given plugin name
     * it will check if the given plugin exists in the plugins folder
     * throws an error if the plugin couldn't be found
     * @throws Exception
     */
    private function validatePluginIsInstalled(string $pluginName): void {
        $searchPattern = "$this->wordpressPath/wp-content/plugins/*{$pluginName}*";
        $matchingDirectories = glob($searchPattern, GLOB_ONLYDIR);
        if (empty($matchingDirectories)) {
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
            'zilch-assistant-plugin' => 'https://gitlab.xel.nl/albert/kameleon-assistant-plugin-zip/-/raw/latest/zilch-assistant-plugin.zip'
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

    private function isProduction(): bool {
        $lowercaseRunLevel = strtolower($this->runlevel);
        return $lowercaseRunLevel === "prod" || $lowercaseRunLevel === "production";
    }

    private function cleanUpScript($removeWordPress = false): void {
        $this->removeFile($this->envFilePath);
        $this->removeDir(__DIR__."/WPResources");
        unlink(__FILE__);
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
            $this->generateResponse();
            $this->cleanUpScript();
        } catch (Error|Exception|Throwable $e) {
            $this->cleanUpScript(true);
            $this->generateResponse($e);
        }
    }
}


function getOptions() {
    // Check if the script is called from the command line or web browser
    if (php_sapi_name() === 'cli') {
        // Get options from the command line
        return getopt("p:d:r:", ["projectName:", "domainName:", "RUN_LEVEL"]);
    } else {
        // Get options from query parameters in the URL
        return [
            'p' => $_GET['projectName'] ?? null,
            'd' => $_GET['domainName'] ?? null,
        ];
    }
}

// get the options of the command
$options = getOptions();
// get the project name and domain name from the short/long options
$projectName = $options['p'] ?? $options['projectName'] ?? null;
$domainName = $options['d'] ?? $options['domainName'] ?? null;
if(!$domainName || !$projectName) {
    echo "Usage: php zilch-wordpress-install-script.php -p <projectName> -d <domainName> OR php zilch-wordpress-install-script.php --projectName=<projectName> --domainName=<domainName>";
    exit(1);
}
// set run level by default to development if it was not given
$runLevel = $options['r'] ?? $options["RUN_LEVEL"] ?? "development";
$helper = new ScriptHelper(__DIR__."/cms", __DIR__."/.db.env", $runLevel);
$helper->installWpScripts($domainName, $projectName);