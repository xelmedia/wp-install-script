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
    private string $documentRoot;
    private string $pharFileDirectory;
    private string $wpcliPharFilePath;
    private string $composerPharFilePath;

    private string $environment;
    private DownloadService $downloadService;
    private WPCommandService $wpCommandService;
    private ComposerCommandService $composerCommandService;

    private WpInstallHelper $wpInstallHelper;
    public function __construct(
        string $documentRoot,
        string $runLevel,
        ?DownloadService $downloadService = null,
        ?WPCommandService $wpCommandService = null,
        ?WpInstallHelper $wpInstallHelper = null,
        ?ComposerCommandService $composerCommandService = null,
    ) {
        $this->documentRoot = $documentRoot;
        $this->pharFileDirectory = "$documentRoot/WPResources";
        $this->wpcliPharFilePath = "$this->pharFileDirectory/wp-cli.phar";
        $this->composerPharFilePath = "$this->pharFileDirectory/composer.phar";

        $this->environment = $runLevel;
        $this->downloadService = $downloadService ?? new DownloadService();
        $this->wpCommandService = $wpCommandService
            ?? new WPCommandService(PHP_BINARY, $this->wpcliPharFilePath, $this->documentRoot);

        $this->composerCommandService = $composerCommandService
            ?? new ComposerCommandService(PHP_BINARY, $this->composerPharFilePath, $this->documentRoot);

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
     * @param $adminEmail
     * @return void
     */
    public function installWpScripts($domainName, $projectName, $adminEmail): void
    {
        try {
            ob_start();
            $this->wpInstallHelper->validatePHPVersion();
            FileHelper::clearDirectory($this->documentRoot, [".env", ".env.zilch", Phar::running(false)]);

            $this->downloadService->downloadPharFile($this->wpcliPharFilePath);
            $this->downloadService->downloadComposerPharFile($this->composerPharFilePath);

            $this->composerCommandService->installBedrock();

            $this->wpCommandService->executeWpCoreInstall($domainName, $projectName, $adminEmail);
            $this->wpCommandService->executeWpReWrite();
            $this->wpCommandService->executeWpLanguageCommands();
        } catch (Error|Exception|Throwable $e) {
            $this->cleanUpScript(true);
            $this->wpInstallHelper->generateResponse($e);
            return;
        }
        $this->wpInstallHelper->generateResponse();
    }

    public function cleanUpScript($removeWordPress = false): void
    {
        FileHelper::removeDir($this->pharFileDirectory);
        if ($this->environment !== "testing") {
            $pharFile = Phar::running(false);
            FileHelper::removeFile($pharFile);
        }
        if ($removeWordPress) {
            FileHelper::clearDirectory($this->documentRoot, [".env", ".env.zilch"]);
        }
    }
}
