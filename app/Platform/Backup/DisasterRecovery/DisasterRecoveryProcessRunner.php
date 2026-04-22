<?php

declare(strict_types=1);

namespace App\Platform\Backup\DisasterRecovery;

use Symfony\Component\Process\Process;

class DisasterRecoveryProcessRunner
{
    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     * @return array{exit_code: int, stdout: string, stderr: string, command: list<string>}
     */
    public function runPhpTool(string $relativeScriptPath, array $arguments = [], array $environment = []): array
    {
        $scriptPath = base_path($relativeScriptPath);
        if (! is_file($scriptPath)) {
            throw new \RuntimeException(sprintf('PHP tool [%s] is missing.', $relativeScriptPath));
        }

        return $this->run(
            array_merge([PHP_BINARY ?: 'php', $scriptPath], $arguments),
            base_path(),
            $environment,
        );
    }

    /**
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment
     * @return array{exit_code: int, stdout: string, stderr: string, command: list<string>}
     */
    public function runArtisan(array $arguments, array $environment = []): array
    {
        $artisanPath = base_path('artisan');
        if (! is_file($artisanPath)) {
            throw new \RuntimeException('artisan is missing from the repository root.');
        }

        return $this->run(
            array_merge([PHP_BINARY ?: 'php', $artisanPath], $arguments),
            base_path(),
            $environment,
        );
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     * @return array{exit_code: int, stdout: string, stderr: string, command: list<string>}
     */
    protected function run(array $command, string $workingDirectory, array $environment = []): array
    {
        $process = new Process(
            $command,
            $workingDirectory,
            array_merge($_ENV, $_SERVER, $environment),
        );
        $process->setTimeout(null);
        $process->run();

        return [
            'exit_code' => (int) ($process->getExitCode() ?? 1),
            'stdout' => trim($process->getOutput()),
            'stderr' => trim($process->getErrorOutput()),
            'command' => $command,
        ];
    }
}
