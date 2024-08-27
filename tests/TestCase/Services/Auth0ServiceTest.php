<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Helpers\CommandExecutor;
use App\TestUtils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use phpmock\spy\Spy;

class Auth0ServiceTest extends TestCase
{
    private WPCommandService|MockObject $commandExecutorService;
    private DownloadService|MockObject $downloadService;
    private Auth0Service|MockObject $auth0Service;

    private CommandExecutor|MockObject $commandExecutor;

    private string $auth0EnvFilePath = __DIR__."/.auth0.env";
    private string $wordpressPath = __DIR__."/cms";

    protected function setUp(): void
    {
        $this->commandExecutorService = $this->createMock(WPCommandService::class);
        $this->downloadService = $this->createMock(DownloadService::class);
        $this->commandExecutorService = $this->createMock(WPCommandService::class);
        $this->commandExecutor = $this->createMock(CommandExecutor::class);
        $this->auth0Service = new Auth0Service(
            $this->commandExecutorService,
            $this->auth0EnvFilePath,
            $this->wordpressPath,
            $this->downloadService,
            $this->commandExecutor
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->auth0EnvFilePath)) {
            unlink($this->auth0EnvFilePath);
        }
        if (file_exists($this->wordpressPath)) {
            exec("rm -rf $this->wordpressPath");
        }
    }

    public function testAddZilchOption(): void
    {
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

    public function testConfigureAuth0(): void
    {
        $authOptions = [
            'ZILCH_AUTH0_CLIENT_SECRET' => base64_encode('client_secret'),
            'ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN' => 'custom_tenant_domain',
            'ZILCH_AUTH0_CLIENT_ID' => 'ID',
            'ZILCH_AUTH0_TENANT_DOMAIN' => 'tenant_domain'
        ];
        TestUtils::createEnvFile($this->auth0EnvFilePath, $authOptions);
        $this->auth0Service = $this->getMockBuilder(Auth0Service::class)
            ->onlyMethods(['installAuth0Plugin'])
            ->setConstructorArgs([
                $this->commandExecutorService,
                $this->auth0EnvFilePath,
                $this->wordpressPath,
                $this->downloadService
            ])
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

    public function testInstallAuth0Plugin(): void
    {
        $auth0tmpDir = "$this->wordpressPath/wp-content/plugins/auth0-tmp";
        $auth0Dir = "$this->wordpressPath/wp-content/plugins/auth0";
        $originalDir = getcwd();

        // Mock the chdir function
        $spy = new Spy('App\Services', 'chdir');
        $spy->enable();

        $this->downloadService->expects($this->once())
            ->method('downloadComposerPharFile')
            ->with("$auth0tmpDir/composer.phar", $auth0tmpDir);

        $execInvocations = 0;
        $this->commandExecutor->expects($this->exactly(3))
            ->method('exec')
            ->with(self::callback(function (string $cmd) use (&$execInvocations, $auth0Dir, $auth0tmpDir) {
                $execInvocations += 1;
                switch ($execInvocations) {
                    case 1:
                        self::assertEquals(PHP_BINARY . " composer.phar require -n symfony/http-client nyholm/psr7 auth0/wordpress:5.x-dev --prefer-source", $cmd);
                        break;
                    case 2:
                        self::assertEquals("mv $auth0tmpDir/vendor/auth0/wordpress/* $auth0Dir", $cmd);
                        break;
                    case 3:
                        self::assertEquals(PHP_BINARY . " $auth0tmpDir/composer.phar install --no-dev --ignore-platform-req=ext-sockets", $cmd);
                        break;
                }
                return true;
            }));

        $this->auth0Service->installAuth0Plugin();

        $firstInvocation = $spy->getInvocations()[0]->getArguments()[0];
        $secondInvocation = $spy->getInvocations()[1]->getArguments()[0];
        $thirdInvocation = $spy->getInvocations()[2]->getArguments()[0];
        $fourthInvocation = $spy->getInvocations()[3]->getArguments()[0];
        self::assertEquals($auth0tmpDir, $firstInvocation);
        self::assertEquals($originalDir, $secondInvocation);
        self::assertEquals($auth0Dir, $thirdInvocation);
        self::assertEquals($originalDir, $fourthInvocation);

        $spy->disable();
    }
}
