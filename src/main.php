<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\DownloadService;
use App\Services\Helpers\CommandExecutor;
use App\Services\Helpers\FileHelper;
use App\Services\WpInstallService;
use Phar;

function getOptions(): array|bool
{
    // Get options from the command line
    return getopt(
        "p:d:e:", // Short options
        [
            "projectName:",
            "domainName:",
            "environment:",
            "admin-email:",
            "static-content-dirs:",
            "update::",
            "backup-folder-path:",
        ]
    );
}
$options = getOptions();
echo "Please enter Git access token, or proceed without which might cause rate limit issues:\n > ";
$gitAccessToken = CommandExecutor::getStdinInputWithTimeout(20);

$projectName = $options['p'] ?? $options['projectName'] ?? null;
$domainName = $options['d'] ?? $options['domainName'] ?? null;
$environment = $options['e'] ?? $options['environment'] ?? "development";
$adminEmail = $options['admin-email'] ?? null;
$staticContentDirs = $options['static-content-dirs'] ?? '';
$isUpdate = filter_var($options['update'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$utilBackupFolderPath = $options['backup-folder-path'] ?? null;
if ($utilBackupFolderPath === '') {
    $utilBackupFolderPath = null;
}

if (!str_contains($adminEmail ?? "", "@")) {
    echo "Given argument --admin-email is not a valid email address: $adminEmail";
    exit(1);
}
if (!$utilBackupFolderPath) {
    echo "Argument --backup-folder-path is required" . PHP_EOL;
    exit(1);
}
if (!$isUpdate && (!$domainName || !$projectName)) {
    echo "Usage: php zilch-wordpress-install-script.php -p <projectName> -d <domainName> -e <environment> " .
        "--admin-email=<email> --backup-folder-path=<path> --static-content-dirs=<dir1,dir2>" . PHP_EOL;
    exit(1);
}

$gitDownloadService = new DownloadService();
$pharFile = Phar::running(false);
$documentRoot = dirname($pharFile);
$wpInstaller = new WpInstallService($documentRoot, $environment, $gitDownloadService);
if ($isUpdate) {
    $wpInstaller->updateWpScripts($gitAccessToken, $utilBackupFolderPath);
} else {
    $wpInstaller->installWpScripts($domainName, $projectName, $adminEmail, $gitAccessToken, $utilBackupFolderPath);
}

// Write deploy scripts to static content dirs from the bundled copy
if (!empty($staticContentDirs)) {
    try {
        $deployScriptSource = $pharFile !== ''
            ? 'phar://' . $pharFile . '/src/Scripts/deploy-zilch.php'
            : __DIR__ . '/Scripts/deploy-zilch.php';

        foreach (explode(',', $staticContentDirs) as $dir) {
            $destination = rtrim(trim($dir), '/') . '/deploy-zilch.php';
            FileHelper::createDir(dirname($destination));
            if (copy($deployScriptSource, $destination) === false) {
                throw new \RuntimeException("Failed to copy bundled deploy script to: $destination");
            }
            chmod($destination, 0755);
        }
    } catch (\Throwable $t) {
        echo "Failed to write deploy-zilch.php to static content dirs";
        echo "\n -> {$t->getMessage()}";
        exit(1);
    }
}

$wpInstaller->cleanUpScript();
