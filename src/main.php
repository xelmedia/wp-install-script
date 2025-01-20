<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

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
$pharFile = Phar::running(false);
$documentRoot = dirname($pharFile);
$wpInstaller = new WpInstallService($documentRoot, $environment);
$wpInstaller->installWpScripts($domainName, $projectName, $adminEmail);

$staticContentDirs = explode(",", $staticContentDirs);
$tag = PACKAGE_VERSION;
$externalFileUrl = "https://raw.githubusercontent.com/xelmedia/wp-install-script/$tag/src/scripts/deploy-zilch.php";

$fileContents = file_get_contents($externalFileUrl);
foreach ($staticContentDirs as $staticContentDir) {
    if (file_put_contents($staticContentDir . "/deploy-zilch.php", $fileContents) === false) {
        echo "Failed to write the file to: {$staticContentDir}";
        exit(1);
    }
}

$wpInstaller->cleanUpScript();
