<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\Exceptions;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use RuntimeException;

final class ScriptExecutionException extends RuntimeException
{
    public static function disabled(): self
    {
        return new self('Script execution is disabled. Enable it via the [ai-sdk-toolbox.scripts.enabled] configuration.');
    }

    public static function cliFileMissing(CliTool $tool): self
    {
        return new self(sprintf('The CLI tool [%s] file [%s] does not exist.', $tool->name, $tool->path));
    }

    public static function unknownScript(Skill $skill, string $script): self
    {
        return new self(sprintf('The script [%s] is not declared by the skill [%s].', $script, $skill->name));
    }

    public static function invalidPath(Skill $skill, string $script): self
    {
        return new self(sprintf('The script [%s] resolves outside the skill [%s] scripts directory.', $script, $skill->name));
    }

    public static function runtimeNotAllowed(string $extension): self
    {
        return new self(sprintf('No runtime is allowed for the [%s] extension. Check the [ai-sdk-toolbox.scripts.runtimes] configuration.', $extension));
    }
}
