<?php
declare(strict_types=1);

namespace App\Services\Helpers;

class CommandExecutor
{

    public function exec(string $command, $output = false): string|false|array
    {
        if ($output) {
            return $this->execOutput($command);
        }
        return exec($command);
    }

    private function execOutput(string $command): array
    {
        $output = [];
        exec($command, $output);
        return $output;
    }

    public function execOrFail(string $command, ?string $errorMessage = null, ?int $code = null): void
    {
        if (!$this->exec($command)) {
            throw new \Exception($errorMessage ?? "Something went wrong executing the command: $command", $code ?? 500);
        }
    }
}
