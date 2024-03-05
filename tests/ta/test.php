<?php
declare(strict_types=1);

class WPInstallScriptTest {
    private function testInstalledPlugins(): void {
        $plugins = exec("wp-env run tests-cli wp plugin list --format=json --path=/var/www/html/cms");
        $plugins = json_decode($plugins);
        $expectedPlugins = [
            'wp-graphql-gutenberg',
            'wp-graphql',
            'zilch-assistant-plugin',
            'contact-form-7'
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
        $languages = exec("wp-env run tests-cli wp core language list --status=installed --path=/var/www/html/cms");
        if(!str_contains($languages, "nl_NL")) {
            throw new Exception("Nederlands is not installed as a core language");
        }
    }
    private function testWPInstallation(): void {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost:8889/wp-login.php');
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.52 Safari/537.17');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!$httpCode == 200 || !strpos($result, '<form name="loginform"') !== false) {
            throw new Exception("The curl output was not successful or it does not contain a login form as expected");
        }
        curl_close($ch);
    }

    public function executeWpInstallScriptsTests() {
        $this->testWPInstallation();
        $this->testInstalledPlugins();
        $this->testWPLanguageInstalled();
    }

}

$testClass = new WPInstallScriptTest();
$testClass->executeWpInstallScriptsTests();

