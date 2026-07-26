<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillInstallException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\Finding;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Install a skill from a local path, a git URL or a GitHub shorthand (vendor/repo)')]
#[Signature('ai:skill-install
    {source : Local path, git URL or GitHub shorthand (vendor/repo)}
    {--path= : Subdirectory of the source when it contains several skills}
    {--all : Install every skill found in the source}
    {--force : Install even when the security scan blocks the skill}')]
final class SkillInstallCommand extends Command
{
    public function handle(SkillInstaller $installer): int
    {
        $source = $this->argument('source');

        if (! is_string($source)) {
            $this->components->error('The source must be a string.');

            return self::FAILURE;
        }

        $path = $this->option('path');
        $force = (bool) $this->option('force');

        try {
            if ((bool) $this->option('all')) {
                return $this->installMany($installer, $source, $force);
            }

            $result = $installer->install(
                source: $source,
                path: is_string($path) ? $path : null,
                force: $force,
                confirm: fn (ScanReport $report): bool => $this->confirmWarnings($report, $force),
            );
        } catch (SkillInstallException $skillInstallException) {
            $this->components->error($skillInstallException->getMessage());

            return self::FAILURE;
        }

        $this->printReport($result->report);

        $this->components->info(sprintf(
            'Skill [%s] installed at [%s] (trust: untrusted). Review it and promote it with ai:skill-trust %s when you are ready.',
            $result->skill->name,
            $result->destination,
            $result->skill->name,
        ));

        return self::SUCCESS;
    }

    private function installMany(SkillInstaller $installer, string $source, bool $force): int
    {
        $result = $installer->installMany(
            source: $source,
            force: $force,
            confirm: fn (ScanReport $report): bool => $this->confirmWarnings($report, $force),
        );

        foreach ($result->installed as $installed) {
            $this->components->info(sprintf('[%s] installed (trust: untrusted).', $installed->skill->name));
        }

        if ($result->skipped !== []) {
            $this->components->warn(sprintf('Skipped (already installed): %s', implode(', ', $result->skipped)));
        }

        if ($result->failed !== []) {
            $this->components->error('Failed to install:');

            foreach ($result->failed as $name => $message) {
                $this->components->bulletList([sprintf('%s — %s', $name, $message)]);
            }
        }

        $this->components->info(sprintf(
            'Done: %d installed, %d skipped, %d failed.',
            $result->installedCount(),
            count($result->skipped),
            count($result->failed),
        ));

        return $result->failed === [] ? self::SUCCESS : self::FAILURE;
    }

    private function confirmWarnings(ScanReport $report, bool $force): bool
    {
        $this->printReport($report);

        if ($force || ! $this->input->isInteractive()) {
            return true;
        }

        return (bool) $this->components->confirm(sprintf(
            'The security scan found %d warnings in skill [%s]. Install anyway?',
            $report->findings->count(),
            $report->skill->name,
        ), false);
    }

    private function printReport(ScanReport $report): void
    {
        if ($report->findings->isEmpty()) {
            $this->components->info('Security scan: no findings.');

            return;
        }

        $this->components->warn(sprintf('Security scan: %s', $report->verdict()->value));

        $this->table(
            ['Severity', 'Rule', 'File', 'Message'],
            $report->findings->map(fn (Finding $finding): array => [
                $finding->severity->value,
                $finding->rule,
                $finding->file.($finding->line !== null ? ':'.$finding->line : ''),
                $finding->message,
            ])->all(),
        );
    }
}
