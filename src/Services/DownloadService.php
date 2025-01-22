<?php
declare(strict_types=1);
namespace App\Services;

use App\Services\Helpers\FileHelper;
use Exception;
use Throwable;
use Xel\Common\Exception\ServiceException;

class DownloadService
{
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
     * @param string|null $bearerToken
     * @return void
     * @throws Exception
     */
    public function downloadFile(string $url, string|array $filePath, string $bearerToken = null): void
    {
        try {
            $context = null;
            if ($bearerToken && strlen($bearerToken) > 0) {
                $options = [
                    "http" => [
                        "header" =>
                            "Authorization: Bearer $bearerToken\r\n" .
                            "User-Agent: PHP-Request\r\n"
                    ]
                ];
                $context = stream_context_create($options);
            }
            $content = file_get_contents($url, false, $context);
            if (!empty($bearerToken)) {
                $content = $this->getContentFromGitApiResponse($content);
            }

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

    public function getContentFromGitApiResponse(mixed $value): string
    {
        $content = is_string($value) ? $value : '';

        $contentArray = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($contentArray)) {
            $contentArray = [];
        }

        $content = base64_decode($contentArray['content'] ?? '', true);
        if (empty($content)) {
            throw new Exception("Unable to decode Github API response. " .
                "Either invalid response, or has no base64 encoded 'content' entry in the JSON response: " .
                "\n - " . json_encode($value));
        }
        return $content;
    }
}
