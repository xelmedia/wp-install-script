<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\FileHelper;
use App\Services\Helpers\WpInstallHelper;
use Error;
use Exception;
use Phar;
use Throwable;

class WpInstallService
{

    private const WORDPRESS_VERSION =  '6.5.2';
    private string $documentRoot;
    private string $wordpressPath;
    private string $pharFileDirectory;
    private string $pharFilePath;
    private string $dbEnvFilePath;
    private string $auth0EnvFilePath;
    private string $environment;
    private DownloadService $downloadService;
    private WPCommandService $wpCommandService;
    private Auth0Service $auth0Service;
    private WpInstallHelper $wpInstallHelper;
    public function __construct(
        string $documentRoot,
        string $runLevel,
        ?DownloadService $downloadService = null,
        ?WPCommandService $wpCommandService = null,
        ?Auth0Service $auth0Service = null,
        ?WpInstallHelper $wpInstallHelper = null
    ) {
        $this->documentRoot = $documentRoot;
        $this->pharFileDirectory = "$documentRoot/WPResources";
        $this->pharFilePath = "$this->pharFileDirectory/wp-cli.phar";
        $this->dbEnvFilePath =  "$documentRoot/.db.env";
        $this->wordpressPath = "$documentRoot/cms";
        $this->auth0EnvFilePath = "$documentRoot/.auth0.env";
        $this->environment = $runLevel;
        $this->downloadService = $downloadService ?? new DownloadService();
        $this->wpCommandService = $wpCommandService
            ?? new WPCommandService(PHP_BINARY, $this->pharFilePath, $this->wordpressPath);
        $this->auth0Service = $auth0Service
            ?? new Auth0Service($this->wpCommandService, $this->auth0EnvFilePath, $this->wordpressPath);
        $this->wpInstallHelper = $wpInstallHelper ?? new WpInstallHelper();
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
            ob_start();
            $this->wpInstallHelper->validatePHPVersion();
            if (FileHelper::pathExists($this->wordpressPath)) {
                $error = new Error("The cms directory already exists", 400);
                $this->wpInstallHelper->generateResponse($error);
                return;
            }
            $this->downloadService->downloadPharFile($this->pharFilePath, $this->pharFileDirectory);
            $this->wpCommandService->executeCoreDownload(self::WORDPRESS_VERSION);
            $this->wpCommandService->executeCreateWpConfig($this->dbEnvFilePath);
            $this->wpCommandService->executeWpCoreInstall($domainName, $projectName);
            $this->wpCommandService->executeWpLanguageCommands();
            ;
            $this->wpCommandService->installPlugins();
            $this->auth0Service->configureAuth0();
            $this->auth0Service->addZilchOptions();
            FileHelper::generateYMLFile($this->wordpressPath);
            $this->wpCommandService->executeWpRewrite();
            $this->wpCommandService->removePlugins();
        } catch (Error|Exception|Throwable $e) {
            $this->cleanUpScript(true);
            $this->wpInstallHelper->generateResponse($e);
            return;
        }
        $this->cleanUpScript();
        $this->wpInstallHelper->generateResponse();
    }

    private function cleanUpScript($removeWordPress = false): void
    {
        FileHelper::removeFile($this->dbEnvFilePath);
        FileHelper::removeDir($this->pharFileDirectory);
        if ($this->environment !== "testing") {
            $pharFile = Phar::running(false);
            FileHelper::removeFile($pharFile);
        }
        FileHelper::removeFile("$this->wordpressPath/wp-cli.yml");
        FileHelper::removeFile($this->auth0EnvFilePath);
        if ($removeWordPress) {
            FileHelper::removeDir($this->wordpressPath);
            FileHelper::removeFile("$this->documentRoot/.htaccess");
        }
    }
}
