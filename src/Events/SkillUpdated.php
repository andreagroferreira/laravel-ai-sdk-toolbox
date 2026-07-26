<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Events;

use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SkillUpdated
{
    use Dispatchable;

    public function __construct(
        public Skill $skill,
        public ?string $previousVersion,
        public ?string $version,
    ) {}
}
