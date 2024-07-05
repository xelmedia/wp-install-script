<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\FileHelper;
use Exception;
use Throwable;

class DownloadService
{

    /**
     * Downloads a wp-cli.phar files that will help executing wordpress commands
     * @throws Exception
     */
    public function downloadPharFile(string $pharFilePath, string $pharFileDirectory): void
    {
        $downloadUrl = "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar";
        $this->downloadFile($downloadUrl, $pharFilePath, $pharFileDirectory);
    }

    public function downloadComposerPharFile(string $composerFilePath, string $composerFileDirectory): void
    {
        $composerUrl = "https://getcomposer.org/composer.phar";
        $this->downloadFile($composerUrl, $composerFilePath, $composerFileDirectory);
    }

    /**
     * Downloads a file given from the given url and places it at the given path
     * It creates the path directory if it does not exist
     * @param string $url
     * @param string $filePath
     * @param string $dirPath
     * @return void
     * @throws Exception
     */
    private function downloadFile(string $url, string $filePath, string $dirPath): void
    {
        try {
            FileHelper::createDir($dirPath);
            $content = file_get_contents($url);
            if (file_put_contents($filePath, $content) !== false) {
                chmod($filePath, 0755);
            }
        } catch (Throwable $e) {
            throw new Exception("Something went wrong while downloading wp phar file", 500);
        }
    }
}
