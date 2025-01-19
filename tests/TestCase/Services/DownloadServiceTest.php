<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

class DownloadServiceTest extends TestCase {
    private $wpcliPharName = "wp-cli.phar";
    private $composerPharName = "composer.phar";
    private $downloadDir = __DIR__ . DIRECTORY_SEPARATOR . "mock-doc-root";
    private DownloadService $downloadService;

    protected function setUp(): void {
        $this->downloadService = new DownloadService();
    }

    protected function tearDown(): void {
        exec("rm -rf $this->downloadDir");
    }

    public function testDownloadWpcliPhar()
    {
        $this->assertFalse(file_exists($pharPath = $this->downloadDir . DIRECTORY_SEPARATOR . $this->wpcliPharName));
        $this->downloadService->downloadPharFile($pharPath);
        $this->assertTrue(file_exists($pharPath));
        $this->assertStringContainsString("WP-CLI", exec("$pharPath --version"));
    }

    public function testDownloadComposerPhar()
    {
        $this->assertFalse(file_exists($pharPath = $this->downloadDir . DIRECTORY_SEPARATOR . $this->composerPharName));
        $this->downloadService->downloadComposerPharFile($pharPath);
        $this->assertTrue(file_exists($pharPath));
        $this->assertStringContainsString("Composer version", exec("$pharPath --version"));
    }
}