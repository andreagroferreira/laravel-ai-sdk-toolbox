<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Management;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use AndreAgroFerreira\AiSdkToolbox\Events\CliToolTrustChanged;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use Illuminate\Support\Collection;

final class CliToolManager
{
    public function __construct(
        private readonly CliToolRegistry $registry,
        private readonly SkillLock $lock,
    ) {}

    /**
     * @return Collection<int, CliTool>
     */
    public function all(): Collection
    {
        return $this->registry->all();
    }

    public function find(string $name): CliTool
    {
        return $this->registry->resolve($name);
    }

    public function trust(string $name, bool $trusted): void
    {
        $this->registry->resolve($name);

        $this->lock->setCliToolTrust($name, $trusted ? Trust::Trusted : Trust::Untrusted);

        CliToolTrustChanged::dispatch($name, $trusted ? Trust::Trusted : Trust::Untrusted);
    }
}
