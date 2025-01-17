<?php

declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\CommandExecutor;
use App\Services\Helpers\FileHelper;
use Exception;
use Throwable;

class ComposerCommandService
{
    private string $phpBin;
    private string $pharFilePath;
    private string $wordpressPath;
    private CommandExecutor $cmdExec;

    public function __construct(string $phpBin, string $pharFilePath, string $composerPath, ?CommandExecutor $cmdExec = null)
    {
        $this->phpBin = $phpBin;
        $this->pharFilePath = $pharFilePath;
        $this->wordpressPath = $composerPath;
        $this->cmdExec = $cmdExec ?? new CommandExecutor();
    }

    /**
     * Executes a command to download the wp core files
     * Throws an error if the wordpress directory doesnt exists at defined the wordpress path
     * @throws Exception
     */
    public function installBedrock(): void
    {
        $respository = "'{\"type\":\"vcs\", \"url\":\"https://github.com/xelmedia/bedrock-headless-zilch.git\"}'";
        $bedrockPath = $this->wordpressPath . "/bedrock";
        $command = "$this->phpBin $this->pharFilePath create-project --repository=$respository roots/bedrock $this->wordpressPath/bedrock";
        $this->cmdExec->execOrFail($command, "Bedrock was not installed successfully");

        $this->cmdExec->execOrFail("mv -f $bedrockPath/* $bedrockPath/.* $this->wordpressPath", "Moving bedrock failed");
        if (!FileHelper::pathExists($this->wordpressPath . "/web")) {
            throw new Exception("The wordpress core was not downloaded successfully", 500);
        }

        FileHelper::removeDir($bedrockPath);
    }
}
