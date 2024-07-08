<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

class DownloadServiceTest extends TestCase {
    private $wpcliPharName = "wp-cli.phar";
    private $composerPharName = "composer.phar";
    private $downloadDir = __DIR__;
    private DownloadService $downloadService;

    protected function setUp(): void {
        $this->downloadService = new DownloadService();
    }

    protected function tearDown(): void {
        $filesToDelete = [
            "$this->downloadDir/$this->wpcliPharName",
            "$this->downloadDir/$this->composerPharName"
        ];
        foreach ($filesToDelete as $fileToDelete) {
            if(file_exists($fileToDelete)) {
                unlink($fileToDelete);
            }
        }
    }

    public function testDownloadPharFile_Success(): void {
        $error = null;
        try {
            $this->downloadService->downloadPharFile("$this->downloadDir/$this->wpcliPharName", $this->downloadDir);

        } catch (\Throwable $e) {
            $error = $e;
        }
        self::assertEquals(null, $error);
    }
    public function testDownloadComposerPharFile_Success(): void {
        $error = null;
        try {
            $this->downloadService->downloadComposerPharFile("$this->downloadDir/$this->composerPharName", $this->downloadDir);

        } catch (\Throwable $e) {
            $error = $e;
        }
        self::assertEquals(null, $error);
    }
}