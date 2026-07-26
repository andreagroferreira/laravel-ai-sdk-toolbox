<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Security;

use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use Illuminate\Support\Collection;

final readonly class ScanReport
{
    /**
     * @param  Collection<int, Finding>  $findings
     */
    public function __construct(
        public Skill $skill,
        public Collection $findings,
    ) {}

    public function verdict(): Verdict
    {
        if ($this->findings->contains(fn (Finding $finding): bool => $finding->severity === Severity::Blocked)) {
            return Verdict::Blocked;
        }

        if ($this->findings->isNotEmpty()) {
            return Verdict::Warnings;
        }

        return Verdict::Safe;
    }

    public function isSafe(): bool
    {
        return $this->verdict() === Verdict::Safe;
    }
}
