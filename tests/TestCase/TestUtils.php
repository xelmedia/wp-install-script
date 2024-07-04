<?php

declare(strict_types=1);

namespace App;

class TestUtils {

    public static function createEnvFile(string $filePath, array $envContent): void {
        $lines = [];
        foreach ($envContent as $key => $value) {
            $lines[] = "$key=$value";
        }
        $envFileContent = implode("\n", $lines);
        file_put_contents($filePath, $envFileContent);
    }
}