<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use App\TestUtils;
use phpmock\MockBuilder;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertTrue;

class FileHelperTest extends TestCase {

    private string $dirPath = __DIR__."/test-dir";

    private string $testFilePath = __DIR__."/test.txt";

    private string $testEnvFilePath = __DIR__."/.test.env";

    private string $ymlFilePath = __DIR__."/wp-cli.yml";

    protected function tearDown(): void {
        $pathsToDelete = [$this->testFilePath,
            $this->testEnvFilePath, $this->ymlFilePath
        ];
        foreach ($pathsToDelete as $path) {
            if(file_exists($path)) {
                unlink($path);
            }
        }
        if(file_exists($this->dirPath)) {
            rmdir($this->dirPath);
        }
    }

    public function testPathExists_pathDoesExist() {
        $result = FileHelper::pathExists(__DIR__);
        self::assertTrue($result);
    }

    public function testPathExists_pathDoesNotExist() {
        $result = FileHelper::pathExists(__DIR__."/WRONG-FOLDER");
        self::assertFalse($result);
    }

    public function testCreateDir(): void {
        FileHelper::createDir($this->dirPath);
        $bool = file_exists($this->dirPath);
        assertTrue($bool);
    }

    public function testRemoveFile() :void {
        self::assertFalse(file_exists($this->testFilePath));
        exec("touch $this->testFilePath");
        self::assertTrue(file_exists($this->testFilePath));
        FileHelper::removeFile($this->testFilePath);
        self::assertFalse(file_exists($this->testFilePath));
    }

    public function testRemoveDir(): void {
        self::assertFalse(file_exists($this->dirPath));
        mkdir($this->dirPath);
        self::assertTrue(file_exists($this->dirPath));
        FileHelper::removeDir($this->dirPath);
        self::assertFalse(file_exists($this->dirPath));
    }

    public function testReadEnvFile(): void {
        $envArray = [
            "key1" => "value1",
            "key2" => "value2",
            "key3" => "value3"
        ];
        TestUtils::createEnvFile($this->testEnvFilePath, $envArray);
        $result = FileHelper::readEnvFile($this->testEnvFilePath);
        self::assertEquals($envArray, $result);
    }

    public function testReadEnvFile_WithComment(): void {
        $envArray = [
            "key1" => "value1",
            "key2" => "value2",
            "key3" => "value3",
            "# This is " => "A comment"
        ];
        TestUtils::createEnvFile($this->testEnvFilePath, $envArray);
        $result = FileHelper::readEnvFile($this->testEnvFilePath);
        $expectedArray = [
            "key1" => "value1",
            "key2" => "value2",
            "key3" => "value3"
        ];
        self::assertEquals($expectedArray, $result);
    }

    public function testGenerateYMLFile(): void {
        FileHelper::generateYMLFile(__DIR__);
        $expectedContent = <<<YAML
apache_modules:
    - mod_rewrite

YAML;
        $content = file_get_contents($this->ymlFilePath);
        assertEquals($expectedContent, $content);
    }
}