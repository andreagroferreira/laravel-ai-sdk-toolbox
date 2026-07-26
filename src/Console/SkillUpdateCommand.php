<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\InvalidSkillException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillInstallException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Update installed skills from their original sources')]
#[Signature('ai:skill-update
    {name? : The skill name (omit to update all installed skills)}
    {--force : Update even when the security scan blocks the skill}')]
final class SkillUpdateCommand extends Command
{
    public function handle(SkillInstaller $installer): int
    {
        $name = $this->argument('name');
        $force = (bool) $this->option('force');

        if (is_string($name)) {
            return $this->updateOne($installer, $name, $force);
        }

        $entries = app(\AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock::class)->entries();

        if ($entries === []) {
            $this->components->info('No installed skills to update.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach (array_keys($entries) as $skillName) {
            $failures += $this->updateOne($installer, $skillName, $force);
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function updateOne(SkillInstaller $installer, string $name, bool $force): int
    {
        try {
            $result = $installer->update($name, $force, fn (ScanReport $report): bool => $this->confirmWarnings($report, $force));

            $this->components->info(sprintf(
                '[%s] updated (%s → %s).',
                $name,
                $result->previousVersion !== null ? mb_substr($result->previousVersion, 0, 8) : 'unknown',
                $result->version !== null ? mb_substr($result->version, 0, 8) : 'unknown',
            ));

            return self::SUCCESS;
        } catch (SkillInstallException|InvalidSkillException $exception) {
            $this->components->error(sprintf('[%s] %s', $name, $exception->getMessage()));

            return self::FAILURE;
        }
    }

    private function confirmWarnings(ScanReport $report, bool $force): bool
    {
        if ($force || ! $this->input->isInteractive()) {
            return true;
        }

        return (bool) $this->components->confirm(sprintf(
            'The security scan found %d warnings in skill [%s]. Update anyway?',
            $report->findings->count(),
            $report->skill->name,
        ), false);
    }
}
