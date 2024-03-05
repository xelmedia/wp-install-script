<?php

class ScriptHelper {
    private string $wordpressPath;
    private string $envFilePath;

    private string $runlevel;

    private const wordpressVersion = '6.4.2';

    private const graphqlPluginVersion = "0.4.1";

    public function __construct($wordpressPath, $envFilePath, $runlevel)
    {
        $this->wordpressPath = $wordpressPath;
        $this->envFilePath = $envFilePath;
        $this->runlevel = $runlevel;
    }

    /**
     * downloads a wp-cli.phar files that will help executing wordpress commands
     * @return void
     */
    private function downloadPharFile(): void
    {
        $downloadUrl = "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar";
        $directory = __DIR__ . "/WPResources";
        // If directory doesnt exists, create one!
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
        $filePath = $directory . "/wp-cli.phar";
        if (file_put_contents($filePath, file_get_contents($downloadUrl)) !== false) {
            chmod($filePath, 0755);
        }
    }

   // Checks if the wordpress dir exists
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
        }
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
        exec('php ' . __DIR__ . '/WPResources/wp-cli.phar core download --version=' . escapeshellarg(self::wordpressVersion) . ' --path=' . $this->wordpressPath);
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
     */
    private function executeCreateWpConfig(): void {
        $envData = $this->readEnvFile();
        exec('php ' . __DIR__ . '/WPResources/wp-cli.phar config create --dbname=' . escapeshellarg($envData["DB_NAME"]) . ' --dbuser=' . escapeshellarg($envData["DB_USER"]) . ' --dbpass=' . escapeshellarg($envData["DB_PASS"]) . ' --dbhost='. escapeshellarg($envData["DB_HOST"] ?? "localhost") .' --path=' . $this->wordpressPath);
    }

    /**
     * it creates a mocked password and email
     * and executes wp core install command given the domain name and project name
     * @param $domainName
     * @param $projectName
     * @return void
     */
    private function executeWpCoreInstall($domainName, $projectName): void {
        $adminPassword = "password";
        $adminEmail = "ta@zilch.nl";
        exec('php ' . __DIR__ . '/WPResources/wp-cli.phar core install --url=' . escapeshellarg($domainName) . ' --title=' . escapeshellarg($projectName) . ' --admin_user=zilch-admin --admin_password=' . escapeshellarg($adminPassword) . ' --admin_email=' . escapeshellarg($adminEmail) . ' --path=' . $this->wordpressPath);
    }

    /**
     * installs and activate dutch language to the wp core files
     * @throws Exception
     */
    private function executeWpLanguageCommands(): void {
        try {
            if(!exec('php ' . __DIR__ . '/WPResources/wp-cli.phar --path=' . escapeshellarg($this->wordpressPath) . ' language core install nl_NL')) {
                throw new Exception("Error executing language core install");
            }
            exec('php ' . __DIR__ . '/WPResources/wp-cli.phar --path=' . escapeshellarg($this->wordpressPath) . ' language core update');
        } catch (\Throwable $t) {
            throw new Exception("Something went wrong while installing and updating the language", 500, $t->getPrevious());
        }
    }

    /**
     * Execute an install plugin command on the wordpress directory
     */
    private function installPlugin($plugin): void {
        exec('php ' . __DIR__ . '/WPResources/wp-cli.phar plugin install ' . escapeshellarg($plugin) . ' --activate --path=' . escapeshellarg($this->wordpressPath));
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
        $wpGraphQlGutenbergVersion = self::graphqlPluginVersion;
        $plugins = [
            'wp-graphql' => 'wp-graphql',
            'wp-graphql-gutenberg' => "https://github.com/pristas-peter/wp-graphql-gutenberg/archive/refs/tags/v$wpGraphQlGutenbergVersion.zip",
            'contact-form-7' => 'contact-form-7',
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
    }

    private function isProduction(): bool {
        $lowercaseRunLevel = strtolower($this->runlevel);
        return $lowercaseRunLevel === "prod" || $lowercaseRunLevel === "production";
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
    public function installWpScripts($domainName, $projectName): void
    {
        try {
            if($this->wordpressDirExists()) {
                $error = new Error("The cms directory already exists", 400);
                $this->generateResponse($error);
                exit($error->getCode());
            }
            $this->downloadPharFile();
            $this->executeCoreDownload();
            $this->executeCreateWpConfig();
            $this->executeWpCoreInstall($domainName, $projectName);
            $this->executeWpLanguageCommands();
            $this->installPlugins();
            $this->generateResponse();
        } catch (Error|Exception|Throwable $e) {
            if($this->isProduction()) {
                $this->removeDir($this->wordpressPath);
                $this->removeFile($this->envFilePath);
                $this->removeDir(__DIR__."/WPResources");
                unlink(__FILE__);
            }
            $this->generateResponse($e);
            exit($e->getCode());
        }
    }
}

// get the options of the command
$options = getopt("p:d:r:", ["projectName:", "domainName:", "RUN_LEVEL"]);

// if the short_options/long_options don't exist it will fail and prints how to use the command properly
if ((!isset($options['p']) || !isset($options['d'])) &&
    (!isset($options['projectName']) || !isset($options['domainName']))) {
    echo "Usage: php WPInstallScript.php -p <projectName> -d <domainName> OR php WPInstallScript.php --projectName=<projectName> --domainName=<domainName>\n";
    exit(1);
}

$projectName = $options['p'] ?? $options['projectName'];
$domainName = $options['d'] ?? $options['domainName'];
$wordpressPath = __DIR__."/cms";
$runLevel = $options['r'] ?? $options["RUN_LEVEL"] ?? "development";
$helper = new ScriptHelper($wordpressPath, __DIR__."/.db.env", $runLevel);
$helper->installWpScripts($domainName, $projectName);