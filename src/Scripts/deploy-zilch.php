<?php

namespace DeployScript;

class DeployZilch
{
    private string $downloadUrl;
    private string $tempZipPath;
    private string $backupDir;
    private string $targetDir;

    public function __construct(string $downloadUrl, string $targetDir = __DIR__)
    {
        $this->downloadUrl = $downloadUrl;
        $this->tempZipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('download_', true) . '.zip';
        $this->backupDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zilch-backup_' . date('Ymd_His');
        $this->targetDir = $targetDir;
    }

    public function run(): void
    {
        try {
            $this->validateRequest();
            $this->validateDomains();
            $this->downloadFile();
            $this->backupExistingFiles();
            $this->extractZip();
            echo json_encode(['status' => 'success', 'message' => 'Build has been downloaded and extracted to dir']);
        } catch (\Exception $e) {
            $this->restoreBackup();
            throw $e;
        } finally {
            $this->purgeVarnish();
            $this->cleanup();
        }
    }

    private function purgeVarnish()
    {

        try {
            $hostname = parse_url($this->downloadUrl, PHP_URL_HOST);
            $serverIp = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, "http://$serverIp/.*");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Host: $hostname",
                "X-Purge-Method: regex"
            ]);

            curl_exec($ch);
        } catch (\Throwable $_) {}
    }

    private function validateRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new \Exception("This script only accepts POST requests.");
        }

        if (empty($this->downloadUrl) || !filter_var($this->downloadUrl, FILTER_VALIDATE_URL)) {
            throw new \Exception("Missing or invalid 'downloadUrl' parameter.");
        }
    }

    private function validateDomains(): void
    {
        $downloadRoot = $this->getRootDomain($this->downloadUrl);
        $serverRoot = $this->getRootDomain('https://' . $_SERVER['SERVER_NAME']);

        if ($downloadRoot !== $serverRoot) {
            throw new \Exception("The provided URL does not match the server's root domain. Expected: $serverRoot, Got: $downloadRoot");
        }
    }

    private function downloadFile(): void
    {
        $downloadHost = parse_url($this->downloadUrl, PHP_URL_HOST);
        $serverIp = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';

        $ch = curl_init($this->downloadUrl);
        curl_setopt($ch, CURLOPT_FILE, fopen($this->tempZipPath, 'w'));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RESOLVE, [
            "$downloadHost:80:$serverIp",
            "$downloadHost:443:$serverIp"
        ]);

        if (!curl_exec($ch) || curl_errno($ch)) {
            throw new \Exception("Error downloading the ZIP file: " . curl_error($ch));
        }

        curl_close($ch);
    }

    private function backupExistingFiles(): void
    {
        if (!is_dir($this->targetDir) || count(glob($this->targetDir . DIRECTORY_SEPARATOR . '*')) === 0) {
            return;
        }

        if (!mkdir($this->backupDir, 0755, true)) {
            throw new \Exception("Failed to create backup directory: $this->backupDir");
        }

        foreach (glob($this->targetDir . DIRECTORY_SEPARATOR . '*') as $file) {
            $backupPath = $this->backupDir . DIRECTORY_SEPARATOR . basename($file);
            if ($file === __FILE__ || $file === $this->tempZipPath) {
                continue;
            }

            rename($file, $backupPath);
        }
    }

    private function extractZip(): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->tempZipPath) === true) {
            $zip->extractTo($this->targetDir);
            $zip->close();
        } else {
            throw new \Exception("Failed to unzip the downloaded file.");
        }
    }

    private function restoreBackup(): void
    {
        if (!is_dir($this->backupDir)) {
            return;
        }

        foreach (glob($this->backupDir . DIRECTORY_SEPARATOR . '*') as $backupFile) {
            $originalPath = $this->targetDir . DIRECTORY_SEPARATOR . basename($backupFile);
            rename($backupFile, $originalPath);
        }

        rmdir($this->backupDir);
    }

    private function cleanup(): void
    {
        if (file_exists($this->tempZipPath)) {
            unlink($this->tempZipPath);
        }
    }

    private function getRootDomain(string $url): string
    {
        $suffixListUrl = 'https://publicsuffix.org/list/public_suffix_list.dat';
        $cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'public_suffix_list.dat';

        // Check if the cache exists and is still valid
        if (!file_exists($cacheFile)) {
            $suffixListContent = file_get_contents($suffixListUrl);
            if ($suffixListContent === false) {
                throw new \Exception("Failed to load the Public Suffix List.");
            }
            file_put_contents($cacheFile, $suffixListContent);
        } else {
            $suffixListContent = file_get_contents($cacheFile);
        }

        // Parse the list and filter out comments
        $suffixes = array_filter(
            explode("\n", $suffixListContent),
            fn($line) => $line && $line[0] !== '/'
        );

        // Parse the host from the URL
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            throw new \Exception("Invalid URL: Unable to parse host.");
        }

        $hostParts = explode('.', $host);
        $count = count($hostParts);

        // Match the longest suffix
        for ($i = 0; $i < $count; $i++) {
            $possibleSuffix = implode('.', array_slice($hostParts, $i));
            if (in_array($possibleSuffix, $suffixes)) {
                // Return the root domain (one part before the matched suffix)
                return $i > 0 ? $hostParts[$i - 1] . '.' . $possibleSuffix : $possibleSuffix;
            }
        }

        // Fallback to the original host if no match
        return $host;
    }
}

try {
    $downloadUrl = $_POST['downloadUrl'] ?? '';
    $deployZilch = new DeployZilch($downloadUrl);
    $deployZilch->run();
} catch (\Exception $e) {
    http_response_code(500);
    json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    return 1;
}
