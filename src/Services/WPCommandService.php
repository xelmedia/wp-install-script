<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\CommandExecutor;
use App\Services\Helpers\FileHelper;
use Exception;
use Throwable;

class WPCommandService
{
    private string $phpBin;
    private string $pharFilePath;
    private string $wordpressPath;
    private const GRAPHQL_GUTENBERG_PLUGIN_VERSION = "0.4.1";
    private const GRAPHQL_PLUGIN_VERSION = "1.22.0";
    private const CONTACTFORM_7_VERSION = "5.9";
    private CommandExecutor $cmdExec;

    private const PLUGINS_TO_INSTALL = [
            'wp-gatsby' => "https://downloads.wordpress.org/plugin/wp-gatsby.zip",
            'wp-graphql' => "https://downloads.wordpress.org/plugin/wp-graphql.". self::GRAPHQL_PLUGIN_VERSION .".zip",
            'wp-graphql-gutenberg' => "https://github.com/pristas-peter/wp-graphql-gutenberg/archive/refs/tags/v".self::GRAPHQL_GUTENBERG_PLUGIN_VERSION.".zip",
            'contact-form-7' => "https://downloads.wordpress.org/plugin/contact-form-7.". self::CONTACTFORM_7_VERSION .".zip",
            'zilch-assistant' => 'https://gitlab.xel.nl/chameleon/kameleon-assistant-plugin-zip/-/raw/latest/zilch-assistant.zip'
        ];

    public function __construct(string $phpBin, string $pharFilePath, string $wordpressPath, ?CommandExecutor $cmdExec = null)
    {
        $this->phpBin = $phpBin;
        $this->pharFilePath = $pharFilePath;
        $this->wordpressPath = $wordpressPath;
        $this->cmdExec = $cmdExec ?? new CommandExecutor();
    }

    /**
     * Executes a command to download the wp core files
     * Throws an error if the wordpress directory doesnt exists at defined the wordpress path
     * @throws Exception
     */
    public function executeCoreDownload(string $wordpressVersion): void
    {
        $wpCommand = "$this->phpBin $this->pharFilePath core download --version=" . escapeshellarg($wordpressVersion) . " --path=" . escapeshellarg($this->wordpressPath);
        $this->cmdExec->execOrFail($wpCommand, "The wordpress core was not downloaded successfully");
        if (!FileHelper::pathExists($this->wordpressPath)) {
            throw new Exception("The wordpress core was not downloaded successfully", 500);
        }
    }

    /**
     * @throws Exception
     */
    public function executeWpReWrite(): void
    {
        $command = "cd $this->wordpressPath && $this->phpBin $this->pharFilePath rewrite structure '/%postname%/' --hard  --path=$this->wordpressPath";
        $this->cmdExec->execOrFail($command, "Something went wrong while executing wp rewrite");
    }

    /**
     * Reads the env file vars and uses it to execute a config create command at the wordpress directory
     * @param string $dbEnvFilePath
     * @return void
     * @throws Exception
     */
    public function executeCreateWpConfig(string $dbEnvFilePath): void
    {
        $envData = FileHelper::readEnvFile($dbEnvFilePath);
        $command = 'config create --dbname=' . escapeshellarg($envData["DB_NAME"]) . ' --dbuser=' . escapeshellarg($envData["DB_USER"]) . ' --dbpass=' . escapeshellarg($envData["DB_PASS"]) . ' --dbhost=' . escapeshellarg($envData["DB_HOST"] ?? "localhost");
        $this->executeWPCommand($command, "Something went wrong while creating wordpress database config");
    }

    /**
     * @throws Exception
     */
    public function executeWpCommand(string $command, string $errorMessage = "", int $errorCode = 500): void
    {
        $wpCommand = $this->formatWpCommand($command);
        $this->cmdExec->execOrFail($wpCommand, $errorMessage ?? "Something went wrong executing the command: $wpCommand", $errorCode);
    }

    public function formatWpCommand(string $command): string
    {
        return "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
    }

    /**
     * Gets option from the options table given option name
     * Convert the option value into an array and returns it
     * Returns null if the option doesnt exist or it cannot be converted to array
     * @param string $option_name
     * @param bool $array
     * @return array|string|null
     */
    public function getOption(string $option_name, $array = true): array|string|null
    {
        $command = $array ? "option get $option_name --format=json" : "option get $option_name";
        $formattedCommand = $this->formatWPCommand($command);
        try {
            $output = $this->cmdExec->exec($formattedCommand, true);
            if (gettype($output) === "array") {
                $array = [];
                foreach ($output as $element) {
                    $decoded = json_decode($element);
                    foreach ($decoded as $key => $value) {
                        $array[$key] = $value;
                    }
                }
                return $array;
            }
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Updates the options in the option table given option name and value
     * @throws Exception
     */
    public function updateOption(string $option_name, mixed $option_value): void
    {
        $json_value = json_encode($option_value);
        $escaped_value = escapeshellarg($json_value);
        $command = "option update $option_name $escaped_value --format=json --autoload=yes";
        $this->executeWPCommand($command, "something went wrong adding the option $option_name");
    }

    /**
     * @throws Exception
     */
    public function validatePluginIsInstalled(string $pluginName): void
    {
        FileHelper::validatePluginIsInstalled($this->wordpressPath, $pluginName);
    }

    /**
     * it creates a mocked password and email
     * and executes wp core install command given the domain name and project name
     * @param $domainName
     * @param $projectName
     * @return void
     * @throws Exception
     */
    public function executeWpCoreInstall($domainName, $projectName): void
    {
        $adminEmail = "email@zilch.nl";
        $command = 'core install --url=' . escapeshellarg($domainName) . ' --title=' . escapeshellarg($projectName) . ' --admin_user=zilch-admin ' . '--admin_email=' . escapeshellarg($adminEmail);
        $this->executeWPCommand($command, "Something went wrong while installing wordpress core for the given domain name: $domainName");
    }

    /**
     * installs and activate dutch language to the wp core files
     * @throws Exception
     */
    public function executeWpLanguageCommands(): void
    {
        $command = 'language core install nl_NL --activate';
        $this->executeWPCommand($command, "Something went wrong while installing and updating the language");
    }

    /**
     * Execute an install plugin command on the wordpress directory
     * @param String $plugin
     * @throws Exception
     */
    public function installPlugin(String $plugin): void
    {
        $command = 'plugin install ' . escapeshellarg($plugin) . ' --activate';
        $this->executeWPCommand($command, "Something went wrong while installing the plugin: $plugin");
    }

    /**
     * It will install and activate plugins
     * It will throw an error if a plugin was not installed successfully
     * @throws Exception
     */
    public function installPlugins(): void
    {
        foreach (self::PLUGINS_TO_INSTALL as $pluginName => $pluginSlug) {
            $this->installPlugin($pluginSlug);
            $this->validatePluginIsInstalled($pluginName);
        }
    }

    public function removePlugins(): void
    {
        $pluginsToNotDelete = array_keys(self::PLUGINS_TO_INSTALL);
        $pluginsToNotDelete[] = 'auth0';
        $excludePlugins = join(",", $pluginsToNotDelete);
        try {
            $command = "plugin uninstall --all --deactivate --exclude=$excludePlugins";
            $this->executeWpCommand($command);
        } catch (Exception $exception) {
            // we dont need to throw an error if it does not exist!
        }
    }
}
