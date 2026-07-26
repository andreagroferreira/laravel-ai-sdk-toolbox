<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Events;

use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SkillTrustChanged
{
    use Dispatchable;

    public function __construct(
        public string $name,
        public Trust $trust,
    ) {}
}
