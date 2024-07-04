<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Helpers\FileHelper;
use Exception;

class GatewayService {

    private string $auth0EnvFilePath;
    private string $runLevel;
    public function __construct(string $auth0EnvFilePath, string $runLevel) {
        $this->auth0EnvFilePath = $auth0EnvFilePath;
        $this->runLevel = $runLevel;
    }

    /**
     * Calls an endpoint on the gateway service which will deploy
     * The manifest on the given wp site and make sure to set it up correctly
     * @param $projectId
     * @param $domainName
     * @return void
     * @throws Exception
     */
    public function deployManifest($projectId, $domainName): void {
        $auth0Array = FileHelper::readEnvFile($this->auth0EnvFilePath);
        $zilchClient = $auth0Array["ZILCH_AUTH0_CLIENT_SECRET"];
        $gatewayHost = $auth0Array["ZILCH_AUTH0_CUSTOM_TENANT_DOMAIN"];
        $postUrl = "https://" . $gatewayHost . "/v1/deploy/manifest";

        $headers = [
            "Content-Type: application/json",
            "X-Zilch-Client-Secret: $zilchClient",
            "X-Zilch-Client-Host: $domainName",
        ];
        $options = [
            'http' => [
                'header' => implode("\r\n", $headers),
                'method' => 'POST',
                'content' => json_encode([
                    'projectId' => $projectId
                ])
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($postUrl, false, $context);
        if($result === false) {
            if(strtolower($this->runLevel ) === "dev" || strtolower($this->runLevel) === "prod") {
                throw new Exception("Error making request to $postUrl", 500);
            }
        }
    }
}