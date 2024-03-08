<?php
declare(strict_types=1);

class WPInstallScriptTest {
    private function testInstalledPlugins(): void {
        $plugins = exec("node_modules/.bin/wp-env run tests-cli wp plugin list --format=json --path=/var/www/html/cms");
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
        $languages = exec("node_modules/.bin/wp-env run tests-cli wp core language list --status=active --path=/var/www/html/cms");
        if(!str_contains($languages, "nl_NL")) {
            throw new Exception("Nederlands is not installed as a core language");
        }
    }
    private function testWPInstallation(): void {
        $loginPage = file_get_contents('http://localhost:8889/wp-login.php');
        if ($loginPage === false) {
            throw new Exception("Failed to retrieve login page");
        }

        if (strpos($loginPage, '<form name="loginform"') === false) {
            throw new Exception("The login page does not contain the expected form");
        }
    }

    public function executeWpInstallScriptsTests() {
        $this->testWPInstallation();
        $this->testInstalledPlugins();
        $this->testWPLanguageInstalled();
    }
}

$testClass = new WPInstallScriptTest();
$testClass->executeWpInstallScriptsTests();

