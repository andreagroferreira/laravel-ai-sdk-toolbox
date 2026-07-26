<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Verify installed skills against the ai-skills.lock integrity hashes')]
#[Signature('ai:skill-verify {name? : Verify a single skill}')]
final class SkillVerifyCommand extends Command
{
    public function handle(SkillRegistry $registry, SkillLock $lock, CliToolRegistry $cliTools): int
    {
        $name = $this->argument('name');
        $names = is_string($name) ? [$name] : array_keys($lock->entries());

        if ($names === [] && $lock->cliToolEntries() === []) {
            $this->components->info('The lock file has no skills to verify.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($names as $skillName) {
            try {
                $skill = $registry->resolve($skillName);
            } catch (SkillNotFoundException) {
                $this->components->error(sprintf('[%s] is locked but no longer on disk.', $skillName));
                $failures++;

                continue;
            }

            $mismatches = $lock->mismatches($skill);

            if ($mismatches === []) {
                $this->components->info(sprintf('[%s] OK.', $skillName));

                continue;
            }

            $this->components->warn(sprintf('[%s] integrity violations:', $skillName));
            $this->components->bulletList($mismatches);
            $failures++;
        }

        foreach ($cliTools->all() as $tool) {
            $mismatches = $lock->cliToolMismatches($tool);

            if ($mismatches === []) {
                $this->components->info(sprintf('cli:%s OK.', $tool->name));

                continue;
            }

            $this->components->warn(sprintf('cli:%s integrity violations:', $tool->name));
            $this->components->bulletList($mismatches);
            $failures++;
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
