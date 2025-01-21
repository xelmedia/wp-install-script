<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\GithubDownloadService;
use App\Services\Helpers\CommandExecutor;
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

if (!str_contains($adminEmail ?? "", "@")) {
    echo "Given argument --admin-email is not a valid email address: $adminEmail";
    exit(1);
}
if (!$domainName || !$projectName) {
    echo "Usage: php zilch-wordpress-install-script.php -p <projectName> -d <domainName> -e <environment> " .
        "--static-content-dirs=<dir1,dir2>" . PHP_EOL;
    exit(1);
}

$gitDownloadService = new GithubDownloadService($gitAccessToken);
$pharFile = Phar::running(false);
$documentRoot = dirname($pharFile);
$wpInstaller = new WpInstallService($documentRoot, $environment, $gitDownloadService);
$wpInstaller->installWpScripts($domainName, $projectName, $adminEmail);

// Write deploy scripts to static content dirs
if (!empty($staticContentDirs)) {
    try {
        $staticContentDirs = array_map(fn($dir) => "$dir/deploy-zilch.php", explode(",", $staticContentDirs));
        $tag = PACKAGE_VERSION;
        $externalFileUrl = "https://raw.githubusercontent.com/xelmedia/wp-install-script/$tag/src/Scripts/deploy-zilch.php";

        $gitDownloadService->downloadFile($externalFileUrl, $staticContentDirs);
    } catch (\Throwable $t) {
        echo "Failed to write the file to: " . implode(",", $staticContentDirs);
        echo "\n -> {$t->getMessage()}";
        exit(1);
    }
}

$wpInstaller->cleanUpScript();
