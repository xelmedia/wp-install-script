<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\FileHelper;
use Exception;
use Throwable;

class GithubDownloadService
{

    public function __construct(private readonly string|null $token = null)
    {
    }

    /**
     * Downloads a wp-cli.phar files that will help executing wordpress commands
     * @throws Exception
     */
    public function downloadPharFile(string $pharFilePath): void
    {
        $downloadUrl = "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar";
        $this->downloadFile($downloadUrl, $pharFilePath);
    }

    public function downloadComposerPharFile(string $composerFilePath): void
    {
        $composerUrl = "https://getcomposer.org/composer.phar";
        $this->downloadFile($composerUrl, $composerFilePath);
    }

    /**
     * Downloads a file given from the given url and places it at the given path
     * It creates the path directory if it does not exist
     * @param string $url
     * @param string|array $filePath
     * @return void
     * @throws Exception
     */
    public function downloadFile(string $url, string|array $filePath): void
    {
        try {
            $context = null;
            if (strlen($this->token ?? "") > 0) {
                $options = [
                    "http" => [
                        "header" => [
                            "Authorization: Bearer $this->token",
                            "User-Agent: PHP-Request" // GitHub requires a User-Agent header
                        ]
                    ]
                ];
                $context = stream_context_create($options);
            }
            $content = file_get_contents($url, false, $context);

            $filePaths = is_string($filePath) ? [$filePath] : $filePath;
            foreach ($filePaths as $destination) {
                FileHelper::createDir(dirname($destination));
                if (file_put_contents($destination, $content) !== false) {
                    chmod($destination, 0755);
                }
            }
        } catch (Throwable $e) {
            throw new Exception("Something went wrong while downloading: $url\n - {$e->getMessage()}", 500);
        }
    }
}
