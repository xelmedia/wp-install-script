<?php

declare(strict_types=1);

namespace App\Services;

use App\TestUtils;
use Exception;
use phpmock\MockBuilder;
use PHPUnit\Framework\TestCase;

class GatewayServiceTest extends TestCase
{
    private string $auth0EnvFilePath;
    private string $runLevel;
    private GatewayService $gatewayService;

    protected function setUp(): void
    {
        $this->auth0EnvFilePath = __DIR__ . "/.auth0.env";
        $this->runLevel = "dev"; // or "prod" as needed
        TestUtils::createEnvFile($this->auth0EnvFilePath, [
            'ZILCH_AUTH0_CLIENT_SECRET' => 'test_client_secret',
            'ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN' => 'test_custom_domain'
        ]);
        $this->gatewayService = new GatewayService($this->auth0EnvFilePath, $this->runLevel);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->auth0EnvFilePath)) {
            unlink($this->auth0EnvFilePath);
        }
    }

    public function testDeployManifestSuccess(): void
    {
        // Mock file_get_contents using phpmock
        $builder = new MockBuilder();
        $builder->setNamespace(__NAMESPACE__)
            ->setName('file_get_contents')
            ->setFunction(
                function () {
                    return '{"status": "success"}';
                }
            );
        $mock = $builder->build();
        $mock->enable();

        // Test successful deployment
        $projectId = "test_project_id";
        $domainName = "test_domain";
        $this->expectNotToPerformAssertions();
        $this->gatewayService->deployManifest($projectId, $domainName);

        $mock->disable();
    }

    public function testDeployManifestFailure(): void
    {
        // Mock file_get_contents using phpmock
        $builder = new MockBuilder();
        $builder->setNamespace(__NAMESPACE__)
            ->setName('file_get_contents')
            ->setFunction(
                function () {
                    return false;
                }
            );
        $mock = $builder->build();
        $mock->enable();

        // Test failed deployment
        $projectId = "test_project_id";
        $domainName = "test_domain";
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error making request to https://test_custom_domain/v1/deploy/manifest");

        $this->gatewayService->deployManifest($projectId, $domainName);

        $mock->disable();
    }
}
