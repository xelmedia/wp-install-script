<?php
$options = getopt("p:d:", ["projectName:", "domainName:"]);

if ((!isset($options['p']) || !isset($options['d'])) &&
    (!isset($options['projectName']) || !isset($options['domainName']))) {
    echo "Usage: php script.php -p <projectName> -d <domainName> OR php script.php --projectName=<projectName> --domainName=<domainName>\n";
    exit(1);
}

$projectName = $options['p'] ?? $options['projectName'];
$domainName = $options['d'] ?? $options['domainName'];
$helper = new ScriptsHelper(__DIR__."/cms", __DIR__."/.db.env");
$helper->installWpScripts($domainName, $projectName);


class ScriptsHelper
{
    private string $wordpressPath;
    private string $envFilePath;

    public function __construct($wordpressPath, $envFilePath)
    {
        $this->wordpressPath = $wordpressPath;
        $this->envFilePath = $envFilePath;
    }

    private function downloadPharFile(): void
    {
        $downloadUrl = "https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar";
        $directory = __DIR__ . "/resources";
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
        $filePath = $directory . "/wp-cli.phar";
        if (file_put_contents($filePath, file_get_contents($downloadUrl)) !== false) {
            chmod($filePath, 0755);
        }
    }

    private function wordpressDirExists(): bool
    {
        return file_exists($this->wordpressPath);
    }

    private function removeFile($path) {
        if(file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @throws Exception
     */
    private function removeDir($dir): void
    {
        if (file_exists($dir)) {
            $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it,
                RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir($dir);
        }
        if ($this->wordpressDirExists()) {
            throw new Exception("The cms directory wasn't deleted successfully, path:  $this->wordpressPath", 500);
        }
    }

    /**
     * @throws Exception
     */
    private function executeCoreDownload(): void
    {
        $wordpressVersion = '6.4.2';
        exec('php ' . __DIR__ . '/resources/wp-cli.phar core download --version=' . escapeshellarg($wordpressVersion) . ' --path=' . $this->wordpressPath);
        if (!$this->wordpressDirExists()) {
            throw new Exception("The wordpress core was not downloaded successfully");
        }
    }

    private function readEnvFile(): array
    {
        $envData = [];
        if (file_exists($this->envFilePath)) {
            $lines = file($this->envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                list($key, $value) = explode('=', $line, 2);
                $envData[trim($key)] = trim($value);
            }
        }
        return $envData;
    }

    private function executeCreateWpConfig(): void
    {
        $envData = $this->readEnvFile();
        exec('php ' . __DIR__ . '/resources/wp-cli.phar config create --dbname=' . escapeshellarg($envData["DB_NAME"]) . ' --dbuser=' . escapeshellarg($envData["DB_USER"]) . ' --dbpass=' . escapeshellarg($envData["DB_PASS"]) . ' --dbhost=localhost --path=' . $this->wordpressPath);
    }

    private function executeWpCoreInstall($domainName, $projectName): void
    {
        $adminPassword = "password";
        $adminEmail = 'test@zilch.nl';
        exec('php ' . __DIR__ . '/resources/wp-cli.phar core install --url=' . escapeshellarg($domainName) . ' --title=' . escapeshellarg($projectName) . ' --admin_user=admin --admin_password=' . escapeshellarg($adminPassword) . ' --admin_email=' . escapeshellarg($adminEmail) . ' --path=' . $this->wordpressPath);
    }

    private function executeWpLanguageCommands(): void
    {
        exec('php ' . __DIR__ . '/resources/wp-cli.phar --path=' . escapeshellarg($this->wordpressPath) . ' language core install nl_NL');
        exec('php ' . __DIR__ . '/resources/wp-cli.phar --path=' . escapeshellarg($this->wordpressPath) . ' language core update');
    }

    private function installPlugin($plugin): void
    {
        exec('php ' . __DIR__ . '/resources/wp-cli.phar plugin install ' . escapeshellarg($plugin) . ' --activate --path=' . escapeshellarg($this->wordpressPath));
    }

    /**
     * @throws Exception
     */
    private function validatePluginIsInstalled(string $pluginName): void
    {
        $searchPattern = "$this->wordpressPath/wp-content/plugins/*{$pluginName}*";
        $matchingDirectories = glob($searchPattern, GLOB_ONLYDIR);
        if (empty($matchingDirectories)) {
            throw new Exception("The plugin $pluginName was not installed correctly");
        }
    }

    /**
     * @throws Exception
     */
    private function installPlugins(): void
    {
        $plugins = [
            'wp-graphql' => 'wp-graphql',
            'wp-graphql-gutenberg' => 'https://github.com/pristas-peter/wp-graphql-gutenberg/archive/refs/tags/v0.4.1.zip',
            'contact-form-7' => 'contact-form-7',
            'zilch-assistant-plugin' => 'https://gitlab.xel.nl/albert/kameleon-assistant-plugin-zip/-/raw/latest/zilch-assistant-plugin.zip'
        ];
        foreach ($plugins as $pluginName => $pluginSlug) {
            $this->installPlugin($pluginSlug);
            $this->validatePluginIsInstalled($pluginName);
        }
    }

    private function generateHtaccessFile($domainName): void
    {
        $htaccessContent = <<<EOD
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteBase /
    
        # Redirect /wp-admin to /cms/wp-admin
        RewriteRule ^wp-admin/?$ https://$domainName/cms/wp-admin [R=301,L]
    
        # Redirect /cms to /cms/wp-admin
        RewriteRule ^cms/?$ https://$domainName/cms/wp-admin [R=301,L]
    </IfModule>
    EOD;
        file_put_contents($this->wordpressPath . '/.htaccess', $htaccessContent);
    }

    private function generateResponse($error = null): void
    {
        $data = ["responseCode" => 200];
        if ($error) {
            $data = [
                "responseCode" => 500,
                "error" => json_encode($error)
            ];
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data) . "\n";
    }

    public function installWpScripts($domainName, $projectName): void
    {
        try {
            $this->removeDir($this->wordpressPath);
            $this->downloadPharFile();
            $this->executeCoreDownload();
            $this->executeCreateWpConfig();
            $this->executeWpCoreInstall($domainName, $projectName);
            $this->executeWpLanguageCommands();
            $this->installPlugins();
            $this->generateHtaccessFile($domainName);

            $this->generateResponse();
        } catch (Exception $e) {
            $this->removeDir($this->wordpressPath);
            $this->removeFile($this->envFilePath);
            $this->removeDir(__DIR__."/resources");
            $this->generateResponse($e);
            unlink(__FILE__);
        }
    }
}