<?php
declare(strict_types=1);

class WPInstallScriptTest {
    private const UPLOAD_MARKER = '/var/www/html/web/app/uploads/zilch-ta/marker.txt';

    private function testInstalledPlugins(): void {
        $plugins = exec("node_modules/.bin/wp-env run tests-cli wp plugin list --format=json");
        $plugins = json_decode($plugins);
        $expectedPlugins = [
            'bedrock-autoloader'
        ];
        foreach ($plugins as $plugin) {
            foreach ($expectedPlugins as $index => $expectedPlugin) {
                if(str_contains($plugin->name, $expectedPlugin)) {
                    if($plugin->status !== "must-use") {
                        throw new Exception("The plugin, $expectedPlugin do exist but its not a must use");
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
        $languages = exec("node_modules/.bin/wp-env run tests-cli wp core language list --status=active");
        if(!str_contains($languages, "nl_NL")) {
            throw new Exception("Nederlands is not installed as a core language");
        }
    }

    private function testZilchAssistantActive(): void {
        $languages = exec("node_modules/.bin/wp-env run tests-cli wp plugin list --status=active");
        if(!str_contains($languages, "zilch-assistant")) {
            throw new Exception("Zilch Assistant plugin is not active");
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

    /**
     * Seeds plugins/uploads state before --update=true.
     */
    public function prepareForUpdate(): void {
        exec("node_modules/.bin/wp-env run tests-cli wp plugin deactivate zilch-assistant");
        exec("node_modules/.bin/wp-env run tests-cli wp plugin install contact-form-7 --activate");
        exec("node_modules/.bin/wp-env run tests-cli wp plugin install wordpress-seo --activate");
        exec("node_modules/.bin/wp-env run tests-cli wp plugin deactivate wordpress-seo");
        exec(
            "node_modules/.bin/wp-env run tests-cli sh -c " . escapeshellarg(
                'mkdir -p /var/www/html/web/app/uploads/zilch-ta && echo zilch-ta-upload-marker > ' . self::UPLOAD_MARKER
            )
        );
        $marker = exec(
            "node_modules/.bin/wp-env run tests-cli sh -c " . escapeshellarg('cat ' . self::UPLOAD_MARKER)
        );
        if (!str_contains($marker, 'zilch-ta-upload-marker')) {
            throw new Exception('Upload marker was not created before update');
        }
    }

    private function testUpdatePreservesUploads(): void {
        $marker = exec(
            "node_modules/.bin/wp-env run tests-cli sh -c " . escapeshellarg(
                'test -f ' . self::UPLOAD_MARKER . ' && cat ' . self::UPLOAD_MARKER
            )
        );
        if (!str_contains($marker, 'zilch-ta-upload-marker')) {
            throw new Exception('Uploads were not restored after Bedrock update');
        }
    }

    private function testUpdatePluginsExistOnDisk(): void {
        foreach (['contact-form-7', 'wordpress-seo', 'zilch-assistant'] as $plugin) {
            $exitCode = 0;
            exec(
                "node_modules/.bin/wp-env run tests-cli wp plugin is-installed $plugin 2>/dev/null",
                $output,
                $exitCode
            );
            if ($exitCode !== 0) {
                throw new Exception("Plugin $plugin is not installed on disk after update");
            }
        }
    }

    private function testUpdatePluginStatesFromDatabase(): void {
        $exitCode = 0;
        exec('node_modules/.bin/wp-env run tests-cli wp plugin is-active contact-form-7 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new Exception('contact-form-7 should stay active after update');
        }
        exec('node_modules/.bin/wp-env run tests-cli wp plugin is-active wordpress-seo 2>/dev/null', $output, $exitCode);
        if ($exitCode === 0) {
            throw new Exception('wordpress-seo should stay inactive after update');
        }
        exec('node_modules/.bin/wp-env run tests-cli wp plugin is-active zilch-assistant 2>/dev/null', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new Exception('zilch-assistant should be active after update');
        }
    }

    /**
     * wp-util removes backup-{timestamp}/ after a successful phar run; ta.sh does the same before these assertions.
     */
    private function testUpdateBackupDirRemoved(): void {
        $leftover = exec(
            "node_modules/.bin/wp-env run tests-cli sh -c " . escapeshellarg(
                'ls -d /var/www/html/backup-* 2>/dev/null || true'
            )
        );
        if (trim($leftover) !== '') {
            throw new Exception('backup-* folder should be removed after update (wp-util cleanup), found: ' . $leftover);
        }
    }

    public function executeWpInstallScriptsTests(): void {
        $this->testWPInstallation();
        $this->testInstalledPlugins();
     //   $this->testWPLanguageInstalled();
        $this->testZilchAssistantActive();
    }

    public function executeWpUpdateTests(): void {
        $this->testWPInstallation();
        $this->testUpdatePreservesUploads();
        $this->testUpdatePluginsExistOnDisk();
        $this->testUpdatePluginStatesFromDatabase();
        $this->testUpdateBackupDirRemoved();
    //    $this->testWPLanguageInstalled();
    }
}

$testClass = new WPInstallScriptTest();
$mode = $argv[1] ?? 'install';

if ($mode === 'prepare-update') {
    $testClass->prepareForUpdate();
} elseif ($mode === 'update') {
    $testClass->executeWpUpdateTests();
} else {
    $testClass->executeWpInstallScriptsTests();
}
