<?php

declare(strict_types=1);

namespace App\Scripts;

use PHPUnit\Framework\TestCase;
use function Composer\Autoload\includeFile;

class DeployZilchScriptTest extends TestCase {
    private string $tmpScriptPath = __DIR__ . "/mock-deploy-dir/deploy-zilch.php";

    protected function setUp(): void
    {
        exec("rm -r " . dirname($this->tmpScriptPath));

        // Verify original script exists in SRC
        $scriptPath = __DIR__ . "/../../../src/Scripts/deploy-zilch.php";
        $this->assertTrue(file_exists($scriptPath));

        // Move the script to a tmp document root
        mkdir(dirname($this->tmpScriptPath));
        file_put_contents($this->tmpScriptPath, file_get_contents($scriptPath));

        // Verify tmp script exists
        $this->assertTrue(file_exists($this->tmpScriptPath));

        // Include once and clean output buffer
        ob_start();
        require_once $this->tmpScriptPath;
        ob_end_clean();
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        exec("rm -r " . dirname($this->tmpScriptPath));
    }

    public function testDeployZilchScript()
    {
        // Server address of this server must equal the download url's host server
        $_SERVER['SERVER_ADDR'] = "213.154.226.42"; // Mock the server address to be gitlab.xel.nl's server
        $_SERVER['REQUEST_METHOD'] = "POST";
        $_SERVER['SERVER_NAME'] = "gitlab.xel.nl"; // Set the current's server name to gitlab.xel.nl
        // Set the download URL the kameleon/zilch assistant plugin repo latest tag
        $_POST['downloadUrl'] = "https://gitlab.xel.nl/chameleon/kameleon-assistant-plugin/-/archive/latest/kameleon-assistant-plugin-latest.zip";

        $deploy = new \DeployZilch($_POST['downloadUrl']);
        $deploy->run();

        // Expect the zip has been unzipped correctly after being downloaded
        $this->assertFileExists(dirname($this->tmpScriptPath) . DIRECTORY_SEPARATOR . "kameleon-assistant-plugin-latest");
        $this->assertFileExists(dirname($this->tmpScriptPath) . DIRECTORY_SEPARATOR . "kameleon-assistant-plugin-latest" . DIRECTORY_SEPARATOR . "zilch-assistant.php");

        $output = json_decode(ob_get_contents() ?? "", true);
        $this->assertEquals($output["status"] ?? "", "success");
        $this->assertEquals($output["message"] ?? "", "Build has been downloaded and extracted to dir");
    }

    public function testDeployZilchScript_noPost()
    {
        $deploy = new \DeployZilch("");
        $thrown = null;
        try {
            $deploy->run();
        } catch (\Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertEquals($thrown->getMessage(), "This script only accepts POST requests.");

        // Expect nothing downloaded and unzipped
        $this->assertEquals(scandir(dirname($this->tmpScriptPath)), [
            '.', '..', 'deploy-zilch.php'
        ]);
    }

    public function testDeployZilchScript_noDownloadUrl()
    {
        $_SERVER['REQUEST_METHOD'] = "POST";
        include_once $this->tmpScriptPath;
        $deploy = new \DeployZilch("");

        $thrown = null;
        try {
            $deploy->run();
        } catch (\Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertEquals($thrown->getMessage(), "Missing or invalid 'downloadUrl' parameter.");

        // Expect nothing downloaded and unzipped
        $this->assertEquals(scandir(dirname($this->tmpScriptPath)), [
            '.', '..', 'deploy-zilch.php'
        ]);
    }

    public function testDeployZilchScript_invalidDownloadUrl()
    {
        $_SERVER['REQUEST_METHOD'] = "POST";
        $_POST['downloadUrl'] = "some.zip";
        include_once $this->tmpScriptPath;
        $deploy = new \DeployZilch("");

        $thrown = null;
        try {
            $deploy->run();
        } catch (\Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertEquals($thrown->getMessage(), "Missing or invalid 'downloadUrl' parameter.");

        // Expect nothing downloaded and unzipped
        $this->assertEquals(scandir(dirname($this->tmpScriptPath)), [
            '.', '..', 'deploy-zilch.php'
        ]);
    }

    public function testDeployZilchScript_missingServerName()
    {
        $_SERVER['REQUEST_METHOD'] = "POST";
        $_POST['downloadUrl'] = "https://some-server.nl/some.zip";

        $deploy = new \DeployZilch($_POST['downloadUrl']);

        $thrown = null;
        try {
            $deploy->run();
        } catch (\Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertEquals($thrown->getMessage(), "Invalid URL: Unable to parse host.");

        // Expect nothing downloaded and unzipped
        $this->assertEquals(scandir(dirname($this->tmpScriptPath)), [
            '.', '..', 'deploy-zilch.php'
        ]);
    }

    public function testDeployZilchScript_serverNameMismatchDownloadUrl()
    {
        $_SERVER['REQUEST_METHOD'] = "POST";
        $_SERVER['SERVER_NAME'] = "wrong-server-name.nl";
        $_POST['downloadUrl'] = "https://some-server.nl/some.zip";

        $deploy = new \DeployZilch($_POST['downloadUrl']);

        $thrown = null;
        try {
            $deploy->run();
        } catch (\Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertEquals($thrown->getMessage(), "The provided URL does not match the server's root domain. Expected: wrong-server-name.nl, Got: some-server.nl");

        // Expect nothing downloaded and unzipped
        $this->assertEquals(scandir(dirname($this->tmpScriptPath)), [
            '.', '..', 'deploy-zilch.php'
        ]);
    }

    public function testDeployZilchScript_localhostAddress_selfSigned()
    {
        $_SERVER['REQUEST_METHOD'] = "POST";
        $_SERVER['SERVER_NAME'] = "28936423984623.nl";
        $_POST['downloadUrl'] = "https://28936423984623.nl/some.zip";

        $deploy = new \DeployZilch($_POST['downloadUrl']);

        $thrown = null;
        try {
            $deploy->run();
        } catch (\Exception $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown);
        $this->assertEquals($thrown->getMessage(), "Error downloading the ZIP file: SSL certificate problem: self-signed certificate");

        // Expect nothing downloaded and unzipped
        $this->assertEquals(scandir(dirname($this->tmpScriptPath)), [
            '.', '..', 'deploy-zilch.php'
        ]);
    }
}