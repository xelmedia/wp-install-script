<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use Exception;

class FileHelper
{
    private static ?CommandExecutor $commandExecutor = null;

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
     * @throws Exception
     */
    public static function removeDir($dirPath): void
    {
        if (!file_exists($dirPath)) {
            return;
        }
        self::commandExecutor()->execOrFail(
            'rm -rf ' . escapeshellarg($dirPath),
            "Something went wrong while removing directory: $dirPath"
        );
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

    /**
     * Moves source tree to the destination path.
     * @throws Exception
     */
    public static function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }
        if (is_dir($destination)) {
            self::removeDir($destination);
        }
        self::createDir($destination);
        $source = rtrim($source, '/');
        $destination = rtrim($destination, '/');
        self::commandExecutor()->execOrFail(
            'cp -a ' . escapeshellarg($source) . '/. ' . escapeshellarg($destination) . '/',
            "Something went wrong while copying directory from $source to $destination"
        );
    }

    /**
     * Returns a list of files to preserve when clearing a directory.
     */
    public static function installPreserveFilenames(?string $utilBackupFolderPath = null): array
    {
        $preserve = ['.env', '.env.zilch'];
        if ($utilBackupFolderPath !== null && $utilBackupFolderPath !== '') {
            $preserve[] = basename($utilBackupFolderPath);
        }

        return $preserve;
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

    private static function commandExecutor(): CommandExecutor
    {
        return self::$commandExecutor ??= new CommandExecutor();
    }
}
