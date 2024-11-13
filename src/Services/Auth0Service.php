<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Helpers\CommandExecutor;
use App\Services\Helpers\FileHelper;
use Exception;

class Auth0Service
{

    private WPCommandService $commandExecutorService;
    private string $auth0EnvFilePath;
    private string $wordpressPath;
    private DownloadService $downloadService;
    private CommandExecutor $cmdExec;

    public function __construct(
        WPCommandService $commandExecutorService,
        string $auth0EnvFilePath,
        string $wordpressPath,
        ?DownloadService $downloadService = null,
        ?CommandExecutor $commandExecutor = null
    ) {
        $this->commandExecutorService = $commandExecutorService;
        $this->auth0EnvFilePath = $auth0EnvFilePath;
        $this->wordpressPath = $wordpressPath;
        $this->downloadService = $downloadService ?? new DownloadService();
        $this->cmdExec = $commandExecutor ?? new CommandExecutor();
    }

    /**
     * Install (using script) and configure auth0 plugin
     * Saves some default auth0 config to the database
     * @throws Exception
     */
    public function configureAuth0(): void
    {
        $this->installAuth0Plugin();
        $this->commandExecutorService->executeWPCommand("plugin activate auth0");
        $this->commandExecutorService->validatePluginIsInstalled("auth0");
        $options = $this->getDefaultAuth0Config();
        foreach ($options as $option_name => $option_value) {
            $current_value = $this->commandExecutorService->getOption($option_name);
            // If option doesn't exist or is different, update it
            if ($current_value === null || count(array_diff_assoc($option_value, $current_value)) > 0) {
                $this->commandExecutorService->updateOption($option_name, $option_value);
            }
        }
    }

    /**
     * It Add/Update the zilch options to the wp options db table
     * @throws Exception
     */
    public function addZilchOptions(): void
    {
        $authOptions = FileHelper::readEnvFile($this->auth0EnvFilePath);
        $options = [
            "zilch_client_secret" => $authOptions["ZILCH_AUTH0_CLIENT_SECRET"],
            "zilch_gateway_host" =>  $authOptions["ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN"]
        ];
        foreach ($options as $option_name => $option_value) {
            $currentValue = $this->commandExecutorService->getOption($option_name, false);
            if ($currentValue !== $option_value) {
                $command = "option update $option_name ". escapeshellarg($option_value);
                $this->commandExecutorService->executeWPCommand($command, "Something went wrong while adding zilch options");
            }
        }
    }

    /**
     * Returns all the default auth0 options and making sure it contains
     * The necessary credentials to do the login request
     * @return array
     */
    private function getDefaultAuth0Config(): array
    {
        $auth0Array = FileHelper::readEnvFile($this->auth0EnvFilePath);
        return array(
            'auth0_state' => array(
                'enable' => 'true'
            ),
            'auth0_accounts' => array(
                'matching' => 'strict',
                'missing' => 'create',
                'default_role' => 'administrator',
                'passwordless' => 'true'
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
                'rolling_sessions' => 'true',
                'refresh_tokens' => 'false'
            )
        );
    }

    /**
     * We need to install auth0 wordpress plugin ^5.x.x
     * because it contains some useful functionality that we can use
     * but the plugin is not completely published to the wordpress plugins store
     * so we need to install it using a composer command
     * @return void
     */
    public function installAuth0Plugin(): void
    {
        $auth0tmpDir = "$this->wordpressPath/wp-content/plugins/auth0-tmp";
        $auth0Dir = "$this->wordpressPath/wp-content/plugins/auth0";
        $originalDir = getcwd();
        // download composer phar to execute composer commands
        $this->downloadService->downloadComposerPharFile("$auth0tmpDir/composer.phar", $auth0tmpDir);
        chdir($auth0tmpDir);
        // download using composer!
        $this->cmdExec
            ->exec(PHP_BINARY . " composer.phar require -n symfony/http-client nyholm/psr7 auth0/wordpress:5.x-dev --prefer-source");
        chdir($originalDir);
        FileHelper::createDir($auth0Dir);
        // remove the content of the wordpress folder to the auth0 folder (which will be used as a default folder for the plugin)
        $this->cmdExec->exec("mv $auth0tmpDir/vendor/auth0/wordpress/* $auth0Dir");
        chdir($auth0Dir);
        // execute composer install
        $this->cmdExec->exec(PHP_BINARY . " $auth0tmpDir/composer.phar install --no-dev --ignore-platform-req=ext-sockets");
        chdir($originalDir);
        // remove the auth0 tmp dir
        FileHelper::removeDir($auth0tmpDir);
    }
}
