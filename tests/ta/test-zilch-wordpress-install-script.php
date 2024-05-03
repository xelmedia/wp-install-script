<?php
declare(strict_types=1);

class WPInstallScriptTest {
    private function testInstalledPlugins(): void {
        $plugins = exec("node_modules/.bin/wp-env run tests-cli wp plugin list --format=json --path=/var/www/html/cms");
        $plugins = json_decode($plugins);
        $expectedPlugins = [
            'wp-graphql-gutenberg',
            'wp-graphql',
            'zilch-assistant',
            'contact-form-7',
            'auth0'
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
        $languages = exec("node_modules/.bin/wp-env run tests-cli wp core language list --status=active --path=/var/www/html/cms");
        if(!str_contains($languages, "nl_NL")) {
            throw new Exception("Nederlands is not installed as a core language");
        }
    }
    private function testWPInstallation(): void {
        $url = "http://localhost:8889/wp-admin";

        $context = stream_context_create([
            'http' => [
                'follow_location' => true,
            ],
        ]);

        file_get_contents($url, false, $context);
        $finalUrl = '';

        for ($i = count($http_response_header) - 1; $i >= 0; $i--) {
            if (str_contains($http_response_header[$i], 'Location: ')) {
                $finalUrl = trim(substr($http_response_header[$i], strlen('Location:')));
                break;
            }
        }
        $domain = $this->readEnvFile(__DIR__."/.auth0.env")["ZILCH_AUTH0_TENANT_DOMAIN"];
        $gatewayHost = $this->readEnvFile(__DIR__."/.auth0.env")["ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN"];
        if (!str_contains($finalUrl, $domain) && !str_contains($finalUrl, $gatewayHost)) {
            throw new Exception("The redirect URL does not represent the universal login page of Auth0: $finalUrl");
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
                list($key, $value) = explode('=', $line, 2);
                $envData[trim($key)] = trim($value);
            }
        }
        return $envData;
    }
}

$testClass = new WPInstallScriptTest();
$testClass->executeWpInstallScriptsTests();

