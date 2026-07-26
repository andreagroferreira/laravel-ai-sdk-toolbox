<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Scripts;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\Exceptions\ScriptExecutionException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Process;

final class LocalScriptExecutor implements ScriptExecutor
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function run(Skill $skill, string $script, array $args): ScriptResult
    {
        if (! $this->config->get('ai-sdk-toolbox.scripts.enabled', true)) {
            throw ScriptExecutionException::disabled();
        }

        if (! in_array($script, $skill->scripts, true)) {
            throw ScriptExecutionException::unknownScript($skill, $script);
        }

        $scriptsPath = realpath($skill->scriptsPath());
        $path = $scriptsPath !== false ? realpath($scriptsPath.DIRECTORY_SEPARATOR.$script) : false;

        if ($scriptsPath === false || $path === false || ! str_starts_with($path, $scriptsPath.DIRECTORY_SEPARATOR)) {
            throw ScriptExecutionException::invalidPath($skill, $script);
        }

        return $this->execute($path, $skill->basePath, $args, []);
    }

    public function runCli(CliTool $tool, array $args): ScriptResult
    {
        if (! $this->config->get('ai-sdk-toolbox.cli_tools.enabled', true)) {
            throw ScriptExecutionException::disabled();
        }

        $path = realpath($tool->path);

        if ($path === false) {
            throw ScriptExecutionException::cliFileMissing($tool);
        }

        return $this->execute($path, dirname($path), $args, $tool->env);
    }

    /**
     * @param  array<int, string>  $args
     * @param  array<int, string>  $extraEnvironmentKeys
     */
    private function execute(string $path, string $workingDirectory, array $args, array $extraEnvironmentKeys): ScriptResult
    {
        /** @var array<string, string> $runtimes */
        $runtimes = $this->config->get('ai-sdk-toolbox.scripts.runtimes', []);
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $binary = $runtimes[$extension] ?? null;

        if ($binary === null) {
            throw ScriptExecutionException::runtimeNotAllowed($extension);
        }

        /** @var array<int, string> $allowedEnvironment */
        $allowedEnvironment = $this->config->get('ai-sdk-toolbox.scripts.environment', ['PATH', 'HOME', 'LANG']);
        $environment = [];

        foreach ([...$allowedEnvironment, ...$extraEnvironmentKeys] as $key) {
            $value = getenv($key);

            if ($value !== false) {
                $environment[] = $key.'='.$value;
            }
        }

        $timeout = $this->config->get('ai-sdk-toolbox.scripts.timeout', 30);

        $result = Process::path($workingDirectory)
            ->timeout(is_int($timeout) ? $timeout : 30)
            ->run(array_map(strval(...), ['env', '-i', ...$environment, $binary, $path, ...$args]));

        $maxOutput = $this->config->get('ai-sdk-toolbox.scripts.max_output', 65536);
        $maxOutput = is_int($maxOutput) ? $maxOutput : 65536;

        return new ScriptResult(
            output: mb_strimwidth($result->output(), 0, $maxOutput, '…'),
            errorOutput: mb_strimwidth($result->errorOutput(), 0, $maxOutput, '…'),
            exitCode: $result->exitCode() ?? 1,
        );
    }
}
