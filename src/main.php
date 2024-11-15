<?php
declare(strict_types=1);

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\WpInstallService;
use Phar;

function getOptions(): array|bool
{
    // Get options from the command line
    return getopt("p:d:e:", ["projectName:", "domainName:", "environment:"]);
}

// get the options of the command
$options = getOptions();
// get the project name and domain name from the short/long options
$projectName = $options['p'] ?? $options['projectName'] ?? null;
$domainName = $options['d'] ?? $options['domainName'] ?? null;
$environment = $options['e'] ?? $options['environment'] ?? "development";
if (!$domainName || !$projectName) {
    echo "Usage: php zilch-wordpress-install-script.php -p <projectName> -d <domainName> OR php zilch-wordpress-install-script.php --projectName=<projectName> --domainName=<domainName>";
    exit(1);
}
$pharFile = Phar::running(false);
$documentRoot = dirname($pharFile);
$wpInstaller = new WpInstallService($documentRoot, $environment);
$wpInstaller->installWpScripts($domainName, $projectName);
