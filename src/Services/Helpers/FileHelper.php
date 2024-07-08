<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHelper
{

    /**
     * Checks if the given path exists (dir or file)
     * @param string $path
     * @return bool
     */
    public static function pathExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Creates a Dir at the given path if it does not exist
     * @param string $path
     * @return void
     */
    public static function createDir(string $path): void
    {
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * Removes a file given path
     * No errors will be thrown if the file doesnt exist
     * @param $path
     * @return void
     */
    public static function removeFile($path): void
    {
        if (file_exists($path)) {
            unlink($path);
            return;
        }
        echo "File at the path: $path doesnt exist";
    }

    /**
     * removes a directory given the path
     * No errors will be thrown if the directory doesnt exist
     */
    public static function removeDir($dirPath): void
    {
        if (file_exists($dirPath)) {
            $it = new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator(
                $it,
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($dirPath);
        }
    }

    /**
     * reads the env file and puts all the vars in an array as $key => $value
     * @param $path
     * @return array
     */
    public static function readEnvFile($path): array
    {
        $envData = [];
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === "#") {
                    continue;
                }
                $parts = explode('=', $line, 2);
                if (count($parts) == 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $envData[$key] = $value;
                }
            }
        }
        return $envData;
    }

    /**
     * validate that plugin is installed given plugin name
     * it will check if the given plugin exists in the plugins folder
     * throws an error if the plugin couldn't be found
     * @throws Exception
     */
    public static function validatePluginIsInstalled(string $wordpressPath, string $pluginName): void
    {
        if (!is_dir("$wordpressPath/wp-content/plugins/$pluginName")) {
            throw new Exception("The plugin $pluginName was not installed correctly", 500);
        }
    }

    public static function generateYMLFile(string $wordpressPath): void
    {
        $content = <<<YAML
apache_modules:
    - mod_rewrite

YAML;
        file_put_contents("$wordpressPath/wp-cli.yml", $content);
    }
}
