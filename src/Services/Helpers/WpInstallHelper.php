<?php

declare(strict_types=1);

namespace App\Services\Helpers;

use Error;
use Exception;
use Throwable;

class WpInstallHelper
{
    private ExitWrapper $exitWrapper;

    public function __construct(?ExitWrapper $exitWrapper = null)
    {
        $this->exitWrapper = $exitWrapper ?? new ExitWrapper();
    }
    public function validatePHPVersion()
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            throw new Exception('PHP version 8.1 or higher is required.');
        }
    }

    /**
     * It will generate a response for the script.
     * Returns 200 if $error is null otherwise returns 500 with the given error.
     *
     * @param Error|Exception|Throwable|null $error
     * @return void
     */
    public function generateResponse(Throwable $error = null): void
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
            $this->exitWrapper->exit($error->getCode());
        }
    }
}
