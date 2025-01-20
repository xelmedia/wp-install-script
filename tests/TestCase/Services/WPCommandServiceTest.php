<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Helpers\CommandExecutor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WPCommandServiceTest extends TestCase
{
    private $phpBin = PHP_BINARY;
    private string $pharFilePath = "wp";
    private string $wordpressPath = __DIR__;
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

    public function testExecuteWpReWrite()
    {
        $this->commandExecutor->expects(self::once())
            ->method("execOrFail")
            ->with(
                "cd $this->wordpressPath && $this->phpBin wp rewrite structure '/%postname%/' --hard",
                "Something went wrong while executing wp rewrite"
            );
        $this->commandExecutorService->executeWpReWrite();
    }

    public function testExecuteWpCommand()
    {
        $someCommand = "some-command --arg=" . guidv4();
        $this->commandExecutor->expects(self::once())
            ->method('execOrFail')
            ->with("$this->phpBin $this->pharFilePath $someCommand");
        $this->commandExecutorService->executeWpCommand($someCommand);
    }

    public function testExecuteWpCoreInstall()
    {
        $this->commandExecutor->expects(self::once())
            ->method('execOrFail')
            ->with("$this->phpBin $this->pharFilePath core install --url='some-domain.nl' --title='my project' --admin_user=zilch-admin --admin_email='email@zilch.website'");
        $this->commandExecutorService->executeWpCoreInstall("some-domain.nl", "my project", "email@zilch.website");
    }

    public function testExecuteWpLanguageCommands()
    {
        $this->commandExecutor->expects(self::once())
            ->method('execOrFail')
            ->with("$this->phpBin $this->pharFilePath language core install nl_NL --activate");
        $this->commandExecutorService->executeWpLanguageCommands();
    }
}

function guidv4($data = null) {
    // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
    $data = $data ?? random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    // Output the 36 character UUID.
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}