<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Events;

use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SkillInstalled
{
    use Dispatchable;

    /**
     * @param  array<int, \AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool>  $cliTools
     */
    public function __construct(
        public Skill $skill,
        public array $cliTools = [],
    ) {}
}
