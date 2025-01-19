<?php

namespace App\Scripts;

class VersionWriter
{
    public static function writeVersion()
    {
        $composerJson = json_decode(file_get_contents(__DIR__ . '/../../composer.json'), true);
        $version = $composerJson['version'] ?? 'unknown';

        $versionFile = __DIR__ . '/../../vendor/version.php';
        $content = "<?php\n\n";
        $content .= "// Auto-generated file. Do not edit manually.\n";
        $content .= "define('PACKAGE_VERSION', '{$version}');\n";

        if (file_put_contents($versionFile, $content) === false) {
            echo "Failed to write version file.\n";
            exit(1);
        }

        echo "Version file generated: {$versionFile}\n";
    }
}
