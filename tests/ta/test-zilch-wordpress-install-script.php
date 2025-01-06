<?php
declare(strict_types=1);

class WPInstallScriptTest {
    private function testInstalledPlugins(): void {
        $plugins = exec("node_modules/.bin/wp-env run tests-cli wp plugin list --format=json --path=/var/www/html");
        $plugins = json_decode($plugins);
        $expectedPlugins = [
            'zilch-assistant'
        ];
        foreach ($plugins as $plugin) {
            foreach ($expectedPlugins as $index => $expectedPlugin) {
                if(str_contains($plugin->name, $expectedPlugin)) {
                    if($plugin->status !== "active") {
                        throw new Exception("The plugin, $expectedPlugin do exist but its not active");
                    }
                    unset($expectedPlugins[$index]);
                    break;
                }
            }
        }
        if(count($expectedPlugins) > 0) {
            $listElements = join(", ", $expectedPlugins);
            throw new Exception("The given plugins were not installed correctly: $listElements");
        }
    }

    private function testWPLanguageInstalled(): void {
        $languages = exec("node_modules/.bin/wp-env run tests-cli wp core language list --status=active --path=/var/www/html");
        if(!str_contains($languages, "nl_NL")) {
            throw new Exception("Nederlands is not installed as a core language");
        }
    }
    private function testWPInstallation(): void {
        $url = "http://localhost:8889/wp-admin";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'follow_location' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
        $finalUrl = '';

        for ($i = count($http_response_header) - 1; $i >= 0; $i--) {
            if (str_contains($http_response_header[$i], 'Location: ')) {
                $finalUrl = trim(substr($http_response_header[$i], strlen('Location:')));
                break;
            }
        }

        if (str_contains($finalUrl, "upgrade.php")) {
            return;
        }
    }

    public function executeWpInstallScriptsTests() {
        $this->testWPInstallation();
        $this->testInstalledPlugins();
        $this->testWPLanguageInstalled();
    }

    private function readEnvFile($path): array {
        $envData = [];
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === "#") {
                    continue;
                }
                list($key, $value) = explode('=', $line, 2);
                $envData[trim($key)] = trim($value);
            }
        }
        return $envData;
    }
}

$testClass = new WPInstallScriptTest();
$testClass->executeWpInstallScriptsTests();

