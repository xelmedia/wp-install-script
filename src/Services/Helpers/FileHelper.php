<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use Exception;
use FilesystemIterator;
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
            $it = new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS);
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

    public static function generateYMLFile(string $wordpressPath): void
    {
        $content = <<<YAML
apache_modules:
    - mod_rewrite

YAML;
        file_put_contents("$wordpressPath/wp-cli.yml", $content);
    }

    public static function clearDirectory($directory, $preserve = []): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $item) {
            $fileName = $item->getFilename();

            // Skip preserved files
            if (in_array($fileName, $preserve)) {
                continue;
            }

            if ($item->isDir()) {
                // Recursively clear subdirectories
                self::clearDirectory($item->getPathname(), $preserve);
                rmdir($item->getPathname());
            } else {
                // Delete files
                unlink($item->getPathname());
            }
        }

        return true;
    }
}
