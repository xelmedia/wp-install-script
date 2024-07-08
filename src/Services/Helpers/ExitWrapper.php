<?php
declare(strict_types=1);

namespace App\Services\Helpers;

use JetBrains\PhpStorm\NoReturn;

class ExitWrapper
{
    #[NoReturn] public function exit(?int $exitCode = null): void
    {
        exit($exitCode ?? 1);
    }
}
