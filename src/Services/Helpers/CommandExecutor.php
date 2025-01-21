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
        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);
        if ($resultCode > 0) {
            throw new \Exception("Command failed: $command\n - " . implode("\n - ", $output), $resultCode);
        }
        return $output;
    }

    private function execOutput(string $command): array
    {
        $output = [];
        exec($command, $output);
        return $output;
    }

    public function execOrFail(string $command, ?string $errorMessage = null, ?int $code = null): void
    {
        $result = $this->exec($command);
        if ($result === false) {
            throw new \Exception($errorMessage ?? "Something went wrong executing the command: $command", $code ?? 500);
        }
    }

    public static function getStdinInputWithTimeout(int $timeoutSeconds): ?string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        // Wait for input on STDIN with a timeout
        $ready = stream_select($read, $write, $except, $timeoutSeconds);
        if ($ready > 0) {
            $stdIn = fgets(STDIN);
            if (is_string($stdIn)) {
                $stdIn = trim($stdIn);
            } else {
                $stdIn = '';
            }
            return strlen($stdIn) > 0 ? $stdIn : null;
        }
        return null;
    }
}
