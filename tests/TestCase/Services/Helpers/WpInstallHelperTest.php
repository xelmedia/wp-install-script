<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use Exception;
use phpmock\MockBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;




class WpInstallHelperTest extends TestCase {
    private ExitWrapper|MockObject $exitWrapper;
    private WpInstallHelper $wpInstallHelper;

    protected function setUp(): void {
        $this->exitWrapper = $this->createMock(ExitWrapper::class);
        $this->wpInstallHelper = new WpInstallHelper($this->exitWrapper);
    }

    public function testGenerateResponseWithoutError(): void {
        $headerMock = (new MockBuilder())
            ->setNamespace("App\Services\Helpers")
            ->setName('header')
            ->setFunction(function ($header) {

            })
            ->build();
        $headerMock->enable();

        ob_start();
        $this->wpInstallHelper->generateResponse();
        $output = ob_get_clean();

        $expectedOutput = json_encode(["responseCode" => 200]) . "\n";
        $this->assertEquals($expectedOutput, $output);

        $headerMock->disable();
    }

    public function testGenerateResponseWithError(): void {
        $headerMock = (new MockBuilder())
            ->setNamespace("App\Services\Helpers")
            ->setName('header')
            ->setFunction(function ($header) {

            })
            ->build();
        $headerMock->enable();

        ob_start();
        $error = new Exception("test exception", 500);
        $this->exitWrapper->expects(self::once())
            ->method("exit")
            ->with(500);
        $this->wpInstallHelper->generateResponse($error);
        $output = ob_get_clean();

        $expectedOutput = json_encode(["responseCode" => 500, "error" => ["message" => "test exception", "code" => 500]]) . "\n";
        $this->assertEquals($expectedOutput, $output);

        $headerMock->disable();
    }

    public function testValidatePHPVersionPassesForHigherVersion(): void
    {
        $versionCompareMock = (new MockBuilder())
            ->setNamespace("App\Services\Helpers")
            ->setName("version_compare")
            ->setFunction(function () {
                return false;
            })
            ->build();
        $versionCompareMock->enable();

        try {
            $this->wpInstallHelper->validatePHPVersion();
            $this->assertTrue(true);
        } catch (Exception $e) {
            $this->fail('Exception should not be thrown for PHP 8.1 or higher');
        }

        $versionCompareMock->disable();
    }

    public function testValidatePHPVersionFailsForLowerVersion(): void
    {
        $versionCompareMock = (new MockBuilder())
            ->setNamespace("App\Services\Helpers")
            ->setName("version_compare")
            ->setFunction(function () {
                return true;
            })
            ->build();
        $versionCompareMock->enable();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('PHP version 8.1 or higher is required.');

        $this->wpInstallHelper->validatePHPVersion();

        $versionCompareMock->disable();
    }
}
