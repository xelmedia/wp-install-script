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
    private const ZILCH_ASSISTANT_SLUG = 'zilch-assistant';

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
        string                  $documentRoot,
        string                  $runLevel,
        ?DownloadService        $downloadService = null,
        ?WPCommandService       $wpCommandService = null,
        ?WpInstallHelper        $wpInstallHelper = null,
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
     * @throws Exception
     */
    private function requireUtilBackupFolder(?string $utilBackupFolderPath): string
    {
        if ($utilBackupFolderPath === null || $utilBackupFolderPath === '') {
            throw new Exception('--backup-folder-path is required');
        }
        if (!is_dir($utilBackupFolderPath)) {
            throw new Exception("Util backup directory does not exist: $utilBackupFolderPath");
        }

        return $utilBackupFolderPath;
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
     * @param string|null $gitAccessToken
     * @param string|null $utilBackupFolderPath wp-util `backup-{timestamp}/` folder (required)
     * @return void
     */
    public function installWpScripts(
        $domainName,
        $projectName,
        $adminEmail,
        ?string $gitAccessToken = null,
        ?string $utilBackupFolderPath = null
    ): void {
        try {
            ob_start();
            $this->wpInstallHelper->validatePHPVersion();

            $utilBackupFolderPath = $this->requireUtilBackupFolder($utilBackupFolderPath);
            FileHelper::clearDirectory(
                $this->documentRoot,
                FileHelper::installPreserveFilenames($utilBackupFolderPath)
            );

            $this->downloadService->downloadPharFile($this->wpcliPharFilePath);
            $this->downloadService->downloadComposerPharFile($this->composerPharFilePath);

            $this->composerCommandService->installBedrock($gitAccessToken);

            $this->wpCommandService->executeWpCoreInstall($domainName, $projectName, $adminEmail);
            $this->wpCommandService->executeActivateZilchPlugin();

            $this->wpCommandService->executeWpReWrite();
            $this->wpCommandService->executeWpLanguageCommands();
        } catch (Error|Exception|Throwable $e) {
            $this->cleanUpScript(true, $utilBackupFolderPath ?? null);
            $this->wpInstallHelper->generateResponse($e);
            return;
        }
        $this->wpInstallHelper->generateResponse();
    }

    /**
     * Bedrock reinstall for an existing site: restore plugins/uploads from wp-util backup-*,
     * wipe tree (preserving env), reinstall Bedrock, restore user content. Does not run `wp core install`.
     *
     * @param string|null $gitAccessToken
     * @param string|null $utilBackupFolderPath Absolute path to wp-util `backup-{timestamp}/` (full docroot snapshot)
     */
    public function updateWpScripts(
        ?string $gitAccessToken = null,
        ?string $utilBackupFolderPath = null
    ): void {
        try {
            ob_start();
            $this->wpInstallHelper->validatePHPVersion();

            $utilBackupFolderPath = $this->requireUtilBackupFolder($utilBackupFolderPath);
            FileHelper::clearDirectory(
                $this->documentRoot,
                FileHelper::installPreserveFilenames($utilBackupFolderPath)
            );

            $this->downloadService->downloadPharFile($this->wpcliPharFilePath);
            $this->downloadService->downloadComposerPharFile($this->composerPharFilePath);

            $this->composerCommandService->installBedrock($gitAccessToken);

            $this->restoreFromUtilBackup($utilBackupFolderPath);

            $this->wpCommandService->executeWpReWrite();
            $this->wpCommandService->executeWpLanguageCommands();
            $this->wpCommandService->executeActivateZilchPlugin();
        } catch (Error|Exception|Throwable $e) {
            $this->cleanUpScript(true, $utilBackupFolderPath ?? null);
            $this->wpInstallHelper->generateResponse($e);
            return;
        }
        $this->wpInstallHelper->generateResponse();
    }

    /**
     * Restores Bedrock user content from a wp-util full-docroot backup (`backup-{timestamp}/web/app/...`).
     *
     * @throws Exception
     */
    private function restoreFromUtilBackup(string $utilBackupRoot): void
    {
        $plugins = $utilBackupRoot . '/web/app/plugins';
        $uploads = $utilBackupRoot . '/web/app/uploads';
        $backupMuPlugins = $utilBackupRoot . '/web/app/mu-plugins';

        if (is_dir($plugins)) {
            FileHelper::copyDirectory($plugins, $this->documentRoot . '/web/app/plugins');
        }
        if (is_dir($uploads)) {
            FileHelper::copyDirectory($uploads, $this->documentRoot . '/web/app/uploads');
        }
        if (is_dir($backupMuPlugins)) {
            FileHelper::copyDirectory($backupMuPlugins, $this->documentRoot . '/web/app/mu-plugins');
        }

        $this->relocateZilchAssistantFromMuPluginsToPlugins();
    }

    /**
     * zilch-assistant must live under plugins/, not mu-plugins. After a full mu-plugins restore,
     * move it when missing from plugins; otherwise drop the mu-plugins copy only.
     *
     * @throws Exception
     */
    private function relocateZilchAssistantFromMuPluginsToPlugins(): void
    {
        $muPluginsZilchAssistant = $this->documentRoot . '/web/app/mu-plugins/' . self::ZILCH_ASSISTANT_SLUG;
        $pluginsZilchAssistant = $this->documentRoot . '/web/app/plugins/' . self::ZILCH_ASSISTANT_SLUG;

        if (!is_dir($muPluginsZilchAssistant)) {
            return;
        }

        if (!is_dir($pluginsZilchAssistant)) {
            FileHelper::copyDirectory($muPluginsZilchAssistant, $pluginsZilchAssistant);
        }

        FileHelper::removeDir($muPluginsZilchAssistant);
    }

    public function cleanUpScript($removeWordPress = false, ?string $utilBackupFolderPath = null): void
    {
        FileHelper::removeDir($this->pharFileDirectory);
        if ($this->environment !== "testing") {
            $pharFile = Phar::running(false);
            FileHelper::removeFile($pharFile);
        }
        if ($removeWordPress) {
            FileHelper::clearDirectory(
                $this->documentRoot,
                FileHelper::installPreserveFilenames($utilBackupFolderPath)
            );
        }
    }
}
