<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\Finding;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillScanner;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Run the security scanner against a skill')]
#[Signature('ai:skill-audit {name : The skill name}')]
final class SkillAuditCommand extends Command
{
    public function handle(SkillRegistry $registry, SkillScanner $scanner): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The skill name must be a string.');

            return self::FAILURE;
        }

        try {
            $skill = $registry->resolve($name);
        } catch (SkillNotFoundException $skillNotFoundException) {
            $this->components->error($skillNotFoundException->getMessage());

            return self::FAILURE;
        }

        $report = $scanner->scan($skill);

        if ($report->findings->isEmpty()) {
            $this->components->info(sprintf('Skill [%s] is clean. No findings.', $name));

            return self::SUCCESS;
        }

        $this->components->warn(sprintf('Skill [%s]: verdict %s', $name, $report->verdict()->value));

        $this->table(
            ['Severity', 'Rule', 'File', 'Message'],
            $report->findings->map(fn (Finding $finding): array => [
                $finding->severity->value,
                $finding->rule,
                $finding->file.($finding->line !== null ? ':'.$finding->line : ''),
                $finding->message,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
