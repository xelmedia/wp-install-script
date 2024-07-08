<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Helpers\CommandExecutor;
use App\TestUtils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;

class WPCommandServiceTest extends TestCase
{
    private $phpBin = PHP_BINARY;
    private string $pharFilePath = "path";
    private string $wordpressPath = __DIR__;
    private string $dbEnvPath = __DIR__."/.db.env";
    private CommandExecutor|MockObject $commandExecutor;
    private WPCommandService $commandExecutorService;

    protected function setUp(): void
    {
        $this->commandExecutor = $this->createMock(CommandExecutor::class);
        $this->commandExecutorService = new WPCommandService(
            $this->phpBin,
            $this->pharFilePath,
            $this->wordpressPath,
            $this->commandExecutor
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbEnvPath)) {
            unlink($this->dbEnvPath);
        }
    }


    public function testExecuteCoreDownload_success(): void
    {
        $wordpressVersion = "1.0.0";
        $expectedCommand = "$this->phpBin $this->pharFilePath core download --version="
            . escapeshellarg($wordpressVersion) . " --path=" . escapeshellarg($this->wordpressPath);
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedCommand, "The wordpress core was not downloaded successfully");

        $this->commandExecutorService->executeCoreDownload($wordpressVersion);
    }

    public function testExecuteCoreDownload_fail(): void
    {
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->willThrowException(new \Exception("exception", 500));
        $this->expectExceptionCode(500);
        $this->commandExecutorService->executeCoreDownload("1.0.0");
    }

    public function testExecuteWpReWrite(): void
    {
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with(
                "cd $this->wordpressPath && $this->phpBin $this->pharFilePath "
                . "rewrite structure '/%postname%/' --hard  --path=$this->wordpressPath"
            );

        $this->commandExecutorService->executeWpReWrite();
    }

    public function testExecuteCreateWpConfig(): void
    {
        $envData = [
            "DB_NAME" => "NAME",
            "DB_HOST" => "HOST",
            "DB_PASS" => "PASS",
            "DB_USER" => "USER"
        ];
        TestUtils::createEnvFile($this->dbEnvPath, $envData);
        $command = 'config create --dbname=' . escapeshellarg($envData["DB_NAME"]) . ' --dbuser=' .
            escapeshellarg($envData["DB_USER"]) . ' --dbpass=' . escapeshellarg($envData["DB_PASS"]) .
            ' --dbhost=' . escapeshellarg($envData["DB_HOST"] ?? "localhost");
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedFormattedCommand);

        $this->commandExecutorService->executeCreateWpConfig($this->dbEnvPath);
    }

    public function testExecuteWpCommand(): void
    {
        $command = "some command";
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedFormattedCommand);
        $this->commandExecutorService->executeWpCommand($command);
    }

    public function testFormatWpCommand(): void
    {
        $command = "some command";
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $result = $this->commandExecutorService->formatWpCommand($command);
        self::assertEquals($expectedFormattedCommand, $result);
    }

    public function testExecuteWpCoreInstall(): void
    {
        $domainName = "domainName";
        $projectName = "projectName";
        $command = 'core install --url=' . escapeshellarg($domainName) . ' --title=' . escapeshellarg($projectName) .
            ' --admin_user=zilch-admin ' . '--admin_email=' . escapeshellarg("email@zilch.nl");
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedFormattedCommand);

        $this->commandExecutorService->executeWpCoreInstall($domainName, $projectName);
    }

    public function testExecuteWpLanguageCommands(): void
    {
        $command = 'language core install nl_NL --activate';
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedFormattedCommand);

        $this->commandExecutorService->executeWpLanguageCommands();
    }

    public function testInstallPlugin(): void
    {
        $plugin = "plugin";
        $command = 'plugin install ' . escapeshellarg($plugin) . ' --activate';
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedFormattedCommand);

        $this->commandExecutorService->installPlugin($plugin);
    }

    public function testUpdateOption(): void
    {
        $optionName = "name";
        $optionValue = ["test" => "test"];
        $command = "option update $optionName ". escapeshellarg(json_encode($optionValue)) .
            " --format=json --autoload=yes";
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedFormattedCommand);

        $this->commandExecutorService->updateOption($optionName, $optionValue);
    }

    public function testGetOptionArrayFalse(): void
    {
        $optionName = "name";
        $command = "option get $optionName";
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("exec")
            ->with($expectedFormattedCommand, true)
            ->willReturn(["options" => "{\"test\": \"test\"}"]);
        $result = $this->commandExecutorService->getOption($optionName, false);
        assertEquals("test", $result["test"]);
    }

    public function testGetOptionArrayTrue(): void
    {
        $optionName = "name";
        $command = "option get $optionName --format=json";
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("exec")
            ->with($expectedFormattedCommand, true)
            ->willReturn(["options" => "{\"test\": \"test\"}"]);
        $result = $this->commandExecutorService->getOption($optionName, true);
        assertEquals("test", $result["test"]);
    }

    public function testRemovePLugins()
    {
        $excludedPlugins = ["wp-gatsby", "wp-graphql", "wp-graphql-gutenberg",
            "contact-form-7", "zilch-assistant", "auth0"];
        $excludedPlugins = join(",", $excludedPlugins);
        $command = "plugin uninstall --all --deactivate --exclude=$excludedPlugins";
        $expectedFormattedCommand = "$this->phpBin $this->pharFilePath $command --path=$this->wordpressPath";
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with($expectedFormattedCommand);

        $this->commandExecutorService->removePlugins();
    }
}
