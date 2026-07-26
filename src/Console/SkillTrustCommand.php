<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Promote a skill to trusted (or demote it back to untrusted)')]
#[Signature('ai:skill-trust {name : The skill name} {--untrust : Demote the skill to untrusted}')]
final class SkillTrustCommand extends Command
{
    public function handle(SkillLock $lock): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The skill name must be a string.');

            return self::FAILURE;
        }

        if (! $lock->has($name)) {
            $this->components->error(sprintf('The skill [%s] is not in the lock file. Only installed skills can change trust.', $name));

            return self::FAILURE;
        }

        $untrust = (bool) $this->option('untrust');
        $lock->setTrust($name, $untrust ? Trust::Untrusted : Trust::Trusted);

        $this->components->info($untrust
            ? sprintf('Skill [%s] demoted to untrusted.', $name)
            : sprintf('Skill [%s] promoted to trusted.', $name));

        return self::SUCCESS;
    }
}
