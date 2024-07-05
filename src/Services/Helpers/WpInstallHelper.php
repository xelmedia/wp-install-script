<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use Error;
use Exception;
use Throwable;

class WpInstallHelper
{
    public static function validatePHPVersion()
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            throw new Exception('PHP version 8.1 or higher is required.');
        }
    }

    /**
     * it will generate a response for the script
     * returns 200 if $error is null otherwise returns 500 with the given error
     * @param Error|Exception|Throwable|null $error
     * @return void
     */
    public static function generateResponse(Error|Exception|Throwable $error = null): void
    {
        $data = ["responseCode" => 200];
        if ($error) {
            $data = [
                "responseCode" => 500,
                "error" => [
                    "message" => $error->getMessage(),
                    "code" => $error->getCode() ?? 500
                ]
            ];
        }
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data) . "\n";
        if ($error) {
            exit($error->getCode());
        }
    }
}
