<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\FileHelper;
use App\Services\Helpers\WpInstallHelper;
use Error;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class WpInstallServiceTest extends TestCase
{
    private string $documentRoot;
    private string $runLevel;
    private DownloadService|MockObject $downloadService;
    private WPCommandService|MockObject $wpCommandService;
    private WpInstallHelper|MockObject $wpInstallHelper;
    private ComposerCommandService|MockObject $composerCommandService;

    private WpInstallService $wpInstallService;

    protected function setUp(): void
    {
        Mock::disableAll();
        $this->downloadService = $this->createMock(DownloadService::class);
        $this->wpCommandService = $this->createMock(WPCommandService::class);
        $this->wpInstallHelper = $this->createMock(WpInstallHelper::class);
        $this->composerCommandService = $this->createMock(ComposerCommandService::class);

        $this->runLevel = "testing";
        $this->documentRoot = __DIR__ . DIRECTORY_SEPARATOR . "mock-doc-root";
        $this->wpInstallService = new WpInstallService(
            $this->documentRoot,
            $this->runLevel,
            $this->downloadService,
            $this->wpCommandService,
            $this->wpInstallHelper,
            $this->composerCommandService
        );
    }

    protected function tearDown(): void
    {
        exec("rm -rf $this->documentRoot");
        Mock::disableAll();
    }

    public function testInstallWpScripts()
    {
        $utilBackupFolderPath = $this->documentRoot . '/backup-install';
        mkdir($utilBackupFolderPath, 0777, true);

        $this->wpInstallHelper->expects(self::once())
            ->method("validatePHPVersion");

        $mockFileExists = (new MockBuilder())
            ->setNamespace("App\Services\Helpers")
            ->setName("file_exists")
            ->setFunction(function () {
                return false;
            })
            ->build();
        $mockFileExists->enable();

        $this->downloadService->expects(self::once())
            ->method("downloadPharFile")
            ->with("$this->documentRoot/WPResources/wp-cli.phar");

        $this->downloadService->expects(self::once())
            ->method("downloadComposerPharFile")
            ->with("$this->documentRoot/WPResources/composer.phar");

        $this->composerCommandService->expects(self::once())
            ->method("installBedrock");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpCoreInstall")
            ->with("d", "p", "email@zilch.website");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpReWrite");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpLanguageCommands");

        $this->wpCommandService->expects(self::once())
            ->method("executeActivateZilchPlugin");

        ob_start();
        $this->wpInstallService->installWpScripts("d", "p", "email@zilch.website", null, $utilBackupFolderPath);
        ob_end_flush();
        ob_get_clean();
    }

    public function testInstallWpScripts_ThrowsError_expectDocRootCleanUp()
    {
        $utilBackupFolderPath = $this->documentRoot . '/backup-install-fail';
        mkdir($utilBackupFolderPath, 0777, true);

        mkdir($this->documentRoot);
        file_put_contents($files[] = $expectNotExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . "some-file.php", "<?php phpinfo(); ?>");
        file_put_contents($files[] = $expectNotExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . "some-file-2.html", "<div>my html</div>");
        file_put_contents($files[] = $expectExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . ".env", "SOME_ENV=1");
        file_put_contents($files[] = $expectExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . ".env.zilch", "ANOTHER_ENV=1");

        $this->assertCount(4, $files);
        $this->assertCount(2, $expectNotExistsFiles);
        $this->assertCount(2, $expectExistsFiles);

        foreach ($files as $file) {
            $this->assertTrue(file_exists($file));
        }

        $this->wpInstallHelper->expects(self::once())
            ->method("validatePHPVersion");

        $mockFileExists = (new MockBuilder())
            ->setNamespace("App\Services\Helpers")
            ->setName("file_exists")
            ->setFunction(function () {
                return false;
            })
            ->build();
        $mockFileExists->enable();

        $this->downloadService->expects(self::once())
            ->method("downloadPharFile")
            ->with("$this->documentRoot/WPResources/wp-cli.phar")
            ->willThrowException(new Error("Wrong url", 500));

        ob_start();
        $this->wpInstallService->installWpScripts("d", "p", "email@zilch.website", null, $utilBackupFolderPath);

        foreach ($expectExistsFiles as $expectExistsFile) {
            $this->assertTrue(file_exists($expectExistsFile));
        }
        foreach ($expectNotExistsFiles as $expectNotExistsFile) {
            $this->assertFalse(file_exists($expectNotExistsFile));
        }
        $this->assertEqualsCanonicalizing(scandir($this->documentRoot), [
            ".",
            "..",
            basename($utilBackupFolderPath),
            ...array_map(fn($f) => basename($f), $expectExistsFiles),
        ]);

        ob_end_flush();
        ob_get_clean();
    }

    public function testUpdateWpScripts_doesNotRunCoreInstall_runsBackupRestoreTail(): void
    {
        $utilBackupRoot = $this->documentRoot . '/backup-util';
        mkdir($utilBackupRoot . '/web/app/plugins', 0777, true);

        $this->wpInstallHelper->expects(self::once())
            ->method("validatePHPVersion");

        $mockFileExists = (new MockBuilder())
            ->setNamespace("App\Services\Helpers")
            ->setName("file_exists")
            ->setFunction(function () {
                return false;
            })
            ->build();
        $mockFileExists->enable();

        $this->downloadService->expects(self::once())
            ->method("downloadPharFile")
            ->with("$this->documentRoot/WPResources/wp-cli.phar");

        $this->downloadService->expects(self::once())
            ->method("downloadComposerPharFile")
            ->with("$this->documentRoot/WPResources/composer.phar");

        $this->composerCommandService->expects(self::once())
            ->method("installBedrock");

        $this->wpCommandService->expects(self::never())
            ->method("executeWpCoreInstall");

        $this->wpCommandService->expects(self::once())
            ->method("executeActivateZilchPlugin");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpReWrite");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpLanguageCommands");

        ob_start();
        $this->wpInstallService->updateWpScripts(null, $utilBackupRoot);
        ob_end_flush();
        ob_get_clean();
    }

    public function testUpdateWpScripts_withoutBackupFolderPath_throws(): void
    {
        $this->wpInstallHelper->expects(self::once())
            ->method("validatePHPVersion");

        $this->wpInstallHelper->expects(self::once())
            ->method('generateResponse')
            ->with(self::callback(static fn ($e) => $e instanceof \Exception
                && str_contains($e->getMessage(), '--backup-folder-path is required')));

        $this->downloadService->expects(self::never())->method('downloadPharFile');

        ob_start();
        $this->wpInstallService->updateWpScripts();
        ob_end_flush();
        ob_get_clean();
    }

    public function testRelocateZilchAssistant_movesFromMuPluginsWhenMissingFromPlugins(): void
    {
        $root = sys_get_temp_dir() . '/zilch-relocate-' . uniqid('', true);
        try {
            $this->mkdirBedrockAppDirs($root);
            mkdir("$root/web/app/mu-plugins/zilch-assistant", 0777, true);
            file_put_contents("$root/web/app/mu-plugins/zilch-assistant/plugin.php", 'mu');

            $this->relocateZilchAssistant($root);

            self::assertSame('mu', file_get_contents("$root/web/app/plugins/zilch-assistant/plugin.php"));
            clearstatcache(true);
            self::assertDirectoryDoesNotExist("$root/web/app/mu-plugins/zilch-assistant");
        } finally {
            FileHelper::removeDir($root);
        }
    }

    public function testRelocateZilchAssistant_removesMuCopyWhenAlreadyUnderPlugins(): void
    {
        $root = sys_get_temp_dir() . '/zilch-relocate-' . uniqid('', true);
        try {
            $this->mkdirBedrockAppDirs($root);
            mkdir("$root/web/app/mu-plugins/zilch-assistant", 0777, true);
            file_put_contents("$root/web/app/mu-plugins/zilch-assistant/stale.php", 'stale');
            mkdir("$root/web/app/plugins/zilch-assistant", 0777, true);
            file_put_contents("$root/web/app/plugins/zilch-assistant/plugin.php", 'plugins');

            $this->relocateZilchAssistant($root);

            self::assertSame('plugins', file_get_contents("$root/web/app/plugins/zilch-assistant/plugin.php"));
            clearstatcache(true);
            self::assertDirectoryDoesNotExist("$root/web/app/mu-plugins/zilch-assistant");
        } finally {
            FileHelper::removeDir($root);
        }
    }

    private function mkdirBedrockAppDirs(string $documentRoot): void
    {
        mkdir("$documentRoot/web/app/mu-plugins", 0777, true);
        mkdir("$documentRoot/web/app/plugins", 0777, true);
    }

    private function relocateZilchAssistant(string $documentRoot): void
    {
        Mock::disableAll();
        $service = new WpInstallService($documentRoot, 'testing');
        $method = new ReflectionMethod(WpInstallService::class, 'relocateZilchAssistantFromMuPluginsToPlugins');
        $method->setAccessible(true);
        $method->invoke($service);
    }
}
