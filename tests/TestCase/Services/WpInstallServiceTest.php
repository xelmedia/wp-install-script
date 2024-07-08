<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\WpInstallHelper;
use Error;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WpInstallServiceTest extends TestCase
{
    private string $documentRoot;
    private string $runLevel;
    private DownloadService|MockObject $downloadService;
    private WPCommandService|MockObject $wpCommandService;
    private Auth0Service|MockObject $auth0Service;
    private GatewayService|MockObject $gatewayService;
    private WpInstallHelper|MockObject $wpInstallHelper;

    private WpInstallService $wpInstallService;

    protected function setUp(): void
    {
        Mock::disableAll();
        $this->downloadService = $this->createMock(DownloadService::class);
        $this->wpCommandService = $this->createMock(WPCommandService::class);
        $this->auth0Service = $this->createMock(Auth0Service::class);
        $this->gatewayService = $this->createMock(GatewayService::class);
        $this->wpInstallHelper = $this->createMock(WpInstallHelper::class);
        $this->runLevel = "testing";
        $this->documentRoot = __DIR__;
        $this->wpInstallService = new WpInstallService(
            $this->documentRoot,
            $this->runLevel,
            $this->downloadService,
            $this->wpCommandService,
            $this->auth0Service,
            $this->gatewayService,
            $this->wpInstallHelper
        );
    }

    protected function tearDown(): void
    {
        rmdir("$this->documentRoot/cms");
        Mock::disableAll();
    }

    public function testInstallWpScripts_cmsExists()
    {
        $this->wpInstallHelper->expects(self::once())
            ->method("validatePHPVersion");

        mkdir("$this->documentRoot/cms");

        $expectedError = new Error("The cms directory already exists", 400);

        $this->wpInstallHelper->expects(self::once())
            ->method("generateResponse")
            ->with(self::callback(function ($parameter) use ($expectedError) {
                return $parameter->getMessage() === $expectedError->getMessage();
            }));

        ob_start();
        $this->wpInstallService->installWpScripts("d1", "p", "i");
        ob_end_flush();
        ob_get_clean();
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
            ->with("$this->documentRoot/WPResources/wp-cli.phar", "$this->documentRoot/WPResources");

        $this->wpCommandService->expects(self::once())
            ->method("executeCoreDownload");

        $this->wpCommandService->expects(self::once())
            ->method("executeCreateWpConfig")
            ->with("$this->documentRoot/.db.env");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpCoreInstall")
            ->with("d", "p");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpLanguageCommands");

        $this->wpCommandService->expects(self::once())
            ->method("installPlugins");

        $this->auth0Service->expects(self::once())
            ->method("configureAuth0");

        $this->auth0Service->expects(self::once())
            ->method("addZilchOptions");

        $this->wpCommandService->expects(self::once())
            ->method("executeWpReWrite");

        $this->wpCommandService->expects(self::once())
            ->method("removePlugins");

        $this->gatewayService->expects(self::once())
            ->method("deployManifest")
            ->with("i", "d");

        ob_start();
        $this->wpInstallService->installWpScripts("d", "p", "i");
        ob_end_flush();
        ob_get_clean();
    }

    public function testInstallWpScripts_ThrowsError()
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
            ->with("$this->documentRoot/WPResources/wp-cli.phar", "$this->documentRoot/WPResources")
            ->willThrowException(new Error("Wrong url", 500));

        $this->gatewayService->expects(self::never())
            ->method("deployManifest");

        ob_start();
        $this->wpInstallService->installWpScripts("d", "p", "i");
        ob_end_flush();
        ob_get_clean();
    }

    public function testInstallWpScripts_deployManifestThrowsError()
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

        $this->gatewayService->expects(self::once())
            ->method("deployManifest")
            ->willThrowException(new Error("Deploying went wrong", 500));

        ob_start();
        $this->wpInstallService->installWpScripts("d", "p", "i");
        ob_end_flush();
        ob_get_clean();
    }
}
