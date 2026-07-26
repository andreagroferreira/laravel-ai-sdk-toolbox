<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Scripts;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;

interface ScriptExecutor
{
    /**
     * @param  array<int, string>  $args
     *
     * @throws Exceptions\ScriptExecutionException
     */
    public function run(Skill $skill, string $script, array $args): ScriptResult;

    /**
     * Run a registered CLI tool, injecting only its declared environment
     * variables into the child process.
     *
     * @param  array<int, string>  $args
     *
     * @throws Exceptions\ScriptExecutionException
     */
    public function runCli(CliTool $tool, array $args): ScriptResult;
}
