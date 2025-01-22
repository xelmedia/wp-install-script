<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\CommandExecutor;
use Exception;

class WPCommandService
{
    private string $phpBin;
    private string $pharFilePath;
    private string $wordpressPath;
    private CommandExecutor $cmdExec;

    public function __construct(
        string $phpBin,
        string $pharFilePath,
        string $wordpressPath,
        ?CommandExecutor $cmdExec = null
    ) {
        $this->phpBin = $phpBin;
        $this->pharFilePath = $pharFilePath;
        $this->wordpressPath = $wordpressPath;
        $this->cmdExec = $cmdExec ?? new CommandExecutor();
    }

    /**
     * @throws Exception
     */
    public function executeWpReWrite(): void
    {
        try {
            $command = "cd $this->wordpressPath && $this->phpBin $this->pharFilePath rewrite structure '/%postname%/' --hard";
            $this->cmdExec->exec($command);
        } catch (\Throwable $t) {
            if (!str_contains($t->getMessage(), "Success: Rewrite structure set")) {
                throw new Exception("Something went wrong while executing wp rewrite", $t->getCode(), $t);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function executeWpCommand(string $command, string $errorMessage = "", int $errorCode = 500): void
    {
        $wpCommand = $this->formatWpCommand($command);
        $this->cmdExec->execOrFail($wpCommand, $errorMessage ?? "Something went wrong executing the command: $wpCommand", $errorCode);
    }

    public function formatWpCommand(string $command): string
    {
        return "$this->phpBin $this->pharFilePath $command";
    }

    /**
     * it creates a mocked password and email
     * and executes wp core install command given the domain name and project name
     * @param $domainName
     * @param $projectName
     * @return void
     * @throws Exception
     */
    public function executeWpCoreInstall($domainName, $projectName, $adminEmail): void
    {
        try {
            $this->executeWpCommand('db clean --yes', "Something went wrong while clearing wp-db, proceeding with installation");
        } catch (\Throwable $t) {
            echo "\nWARNING: {$t->getMessage()}";
        }

        $command = 'core install --url=' . escapeshellarg($domainName) .
            ' --title=' . escapeshellarg($projectName) .
            ' --admin_user=zilch-admin ' . '--admin_email=' . escapeshellarg($adminEmail);

        $this->executeWPCommand($command, "Something went wrong while installing wordpress core for the given domain name: $domainName");
    }

    /**
     * installs and activate dutch language to the wp core files
     * @throws Exception
     */
    public function executeWpLanguageCommands(): void
    {
        $command = 'language core install nl_NL --activate';
        $this->executeWPCommand($command, "Something went wrong while installing and updating the language");
    }
}
