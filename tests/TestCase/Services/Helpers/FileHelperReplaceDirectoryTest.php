<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use PHPUnit\Framework\TestCase;

class FileHelperReplaceDirectoryTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/zilch-replace-dir-' . uniqid('', true);
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->base)) {
            FileHelper::removeDir($this->base);
        }
    }

    public function testReplaceDirectory_replacesDestinationCompletely(): void
    {
        $source = $this->base . '/source';
        $dest = $this->base . '/dest';
        mkdir($source . '/contact-form-7', 0777, true);
        file_put_contents($source . '/contact-form-7/plugin.php', 'cf7');
        mkdir($dest . '/zilch-assistant', 0777, true);
        file_put_contents($dest . '/zilch-assistant/plugin.php', 'zilch');

        FileHelper::copyDirectory($source, $dest);

        self::assertFileExists($dest . '/contact-form-7/plugin.php');
        self::assertDirectoryDoesNotExist($dest . '/zilch-assistant');
    }
}
