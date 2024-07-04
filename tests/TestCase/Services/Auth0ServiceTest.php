<?php
declare(strict_types=1);

namespace App\Services;

use App\TestUtils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class Auth0ServiceTest extends TestCase {
    private WPCommandService|MockObject $commandExecutorService;
    private DownloadService|MockObject $downloadService;
    private Auth0Service|MockObject $auth0Service;

    private string $auth0EnvFilePath = __DIR__."/.auth0.env";
    private string $wordpressPath = __DIR__."/cms";
    protected function setUp(): void {
        $this->commandExecutorService = $this->createMock(WPCommandService::class);
        $this->downloadService = $this->createMock(DownloadService::class);
        $this->auth0Service = new Auth0Service($this->commandExecutorService, $this->auth0EnvFilePath, $this->wordpressPath, $this->downloadService);
    }

    protected function tearDown(): void {
        if(file_exists($this->auth0EnvFilePath)) {
            unlink($this->auth0EnvFilePath);
        }
    }

    public function testAddZilchOption(): void {
        $authOptions = [
            'ZILCH_AUTH0_CLIENT_SECRET' => 'client_secret',
            'ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN' => 'custom_tenant_domain'
        ];
        TestUtils::createEnvFile($this->auth0EnvFilePath, $authOptions);
        $this->commandExecutorService->expects(self::exactly(2))
            ->method("getOption")
            ->willReturn("something");

        $this->commandExecutorService->expects(self::exactly(2))
            ->method("executeWpCommand");

        $this->auth0Service->addZilchOptions();
    }

    public function testConfigureAuth0(): void {
        $authOptions = [
            'ZILCH_AUTH0_CLIENT_SECRET' => base64_encode('client_secret'),
            'ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN' => 'custom_tenant_domain',
            'ZILCH_AUTH0_CLIENT_ID' => 'ID',
            'ZILCH_AUTH0_TENANT_DOMAIN' => 'tenant_domain'
        ];
        TestUtils::createEnvFile($this->auth0EnvFilePath, $authOptions);
        $this->auth0Service = $this->getMockBuilder(Auth0Service::class)
            ->onlyMethods(['installAuth0Plugin'])
            ->setConstructorArgs([$this->commandExecutorService, $this->auth0EnvFilePath, $this->wordpressPath, $this->downloadService])
            ->getMock();

        $this->auth0Service->expects(self::once())
            ->method('installAuth0Plugin');

        $this->commandExecutorService->expects(self::once())
            ->method('executeWPCommand')
            ->with('plugin activate auth0');

        $this->commandExecutorService->expects(self::once())
            ->method('validatePluginIsInstalled')
            ->with('auth0');

        $this->commandExecutorService->expects(self::exactly(6))
            ->method('getOption')
            ->willReturn(null);

        $this->commandExecutorService->expects(self::exactly(6))
            ->method('updateOption');

        $this->auth0Service->configureAuth0();
    }
}