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

    public function testGetContentsFromResponse_noString()
    {
        $thrown = null;
        try {
            $result = [];
            $this->downloadService->getContentFromGitApiResponse($result);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        self::assertNotNull($thrown);
        self::assertStringContainsString('Unable to decode Github API response.', $thrown->getMessage());
    }

    public function testGetContentsFromResponse_invalidString()
    {
        $thrown = null;
        try {
            $result = 'some string';
            $this->downloadService->getContentFromGitApiResponse($result);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        self::assertNotNull($thrown);
        self::assertStringContainsString('Unable to decode Github API response.', $thrown->getMessage());
    }

    public function testGetContentsFromResponse_noContentInJson()
    {
        $thrown = null;
        try {
            $result = '{"someKey":"someVal"}';
            $this->downloadService->getContentFromGitApiResponse($result);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        self::assertNotNull($thrown);
        self::assertStringContainsString('Unable to decode Github API response.', $thrown->getMessage());
    }

    public function testGetContentsFromResponse()
    {
        $result = '{"content":"'. base64_encode("my-encoded-val") . '"}';
        $response = $this->downloadService->getContentFromGitApiResponse($result);
        self::assertEquals("my-encoded-val", $response);
    }
}