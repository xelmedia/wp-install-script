<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\WpInstallHelper;
use Error;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function KevinGH\Box\FileSystem\filename;

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
            ->with("d", "p");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpReWrite");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpLanguageCommands");

        ob_start();
        $this->wpInstallService->installWpScripts("d", "p");
        ob_end_flush();
        ob_get_clean();
    }

    public function testInstallWpScripts_ThrowsError_expectDocRootCleanUp()
    {

        // Prepare some data that was created during test in the doc root
        mkdir($this->documentRoot);
        file_put_contents($files[] = $expectNotExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . "some-file.php", "<?php phpinfo(); ?>");
        file_put_contents($files[] = $expectNotExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . "some-file-2.html", "<div>my html</div>");
        file_put_contents($files[] = $expectExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . ".env", "SOME_ENV=1");
        file_put_contents($files[] = $expectExistsFiles[] = $this->documentRoot . DIRECTORY_SEPARATOR . ".env.zilch", "ANOTHER_ENV=1");

        // Expect files exist
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
        $this->wpInstallService->installWpScripts("d", "p");

        // Expect only the .env files exist and other files to be cleaner up after failure
        foreach ($expectExistsFiles as $expectExistsFile) {
            $this->assertTrue(file_exists($expectExistsFile));
        }
        foreach ($expectNotExistsFiles as $expectNotExistsFile) {
            $this->assertFalse(file_exists($expectNotExistsFile));
        }
        // Also verify no other files or dirs exist:
        $this->assertEquals(scandir($this->documentRoot), [
            ".", "..", ...array_map(fn($f) => filename($f), $expectExistsFiles)
        ]);

        ob_end_flush();
        ob_get_clean();
    }
}
