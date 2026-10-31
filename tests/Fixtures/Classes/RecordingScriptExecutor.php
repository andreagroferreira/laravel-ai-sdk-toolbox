<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\LocalScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\ScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\ScriptResult;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;

final class RecordingScriptExecutor implements ScriptExecutor
{
    /**
     * @var array<int, array{target: string, args: array<int, string>}>
     */
    public array $runs = [];

    public function __construct(
        private readonly LocalScriptExecutor $executor,
    ) {}

    /**
     * @param  array<int, string>  $args
     */
    public function run(Skill $skill, string $script, array $args): ScriptResult
    {
        $this->runs[] = ['target' => $skill->name.'/'.$script, 'args' => $args];

        return $this->executor->run($skill, $script, $args);
    }

    /**
     * @param  array<int, string>  $args
     */
    public function runCli(CliTool $tool, array $args): ScriptResult
    {
        $this->runs[] = ['target' => $tool->name, 'args' => $args];

        return $this->executor->runCli($tool, $args);
    }
}
