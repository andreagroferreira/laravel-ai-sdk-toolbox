<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Management;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use AndreAgroFerreira\AiSdkToolbox\Events\CliToolsInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillTrustChanged;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillUninstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillUpdated;
use AndreAgroFerreira\AiSdkToolbox\Skills\InstallManyResult;
use AndreAgroFerreira\AiSdkToolbox\Skills\InstallResult;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillScanner;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use AndreAgroFerreira\AiSdkToolbox\Skills\UpdateResult;
use Illuminate\Support\Collection;

final class SkillManager
{
    public function __construct(
        private readonly SkillInstaller $installer,
        private readonly SkillRegistry $registry,
        private readonly SkillScanner $scanner,
        private readonly SkillLock $lock,
        private readonly CliToolRegistry $cliTools,
    ) {}

    /**
     * @return Collection<int, Skill>
     */
    public function all(): Collection
    {
        return $this->registry->all();
    }

    public function find(string $name): Skill
    {
        return $this->registry->resolve($name);
    }

    /**
     * Install skills from a source. Third-party installs always land untrusted.
     */
    public function install(string $source, ?string $path = null, bool $all = false, bool $force = false, bool $acceptWarnings = false): InstallResult|InstallManyResult
    {
        $confirm = fn (): bool => $acceptWarnings;

        if ($all) {
            $result = $this->installer->installMany($source, $force, $confirm);

            foreach ($result->installed as $installed) {
                SkillInstalled::dispatch($installed->skill, $installed->cliTools);
            }

            if ($result->cliTools !== []) {
                CliToolsInstalled::dispatch($result->cliTools, $source);
            }

            return $result;
        }

        $result = $this->installer->install($source, $path, $force, $confirm);

        SkillInstalled::dispatch($result->skill, $result->cliTools);

        if ($result->cliTools !== []) {
            CliToolsInstalled::dispatch($result->cliTools, $source);
        }

        return $result;
    }

    public function uninstall(string $name): void
    {
        $this->installer->uninstall($name);

        SkillUninstalled::dispatch($name);
    }

    /**
     * Update an installed skill from its original source.
     */
    public function update(string $name, bool $force = false, bool $acceptWarnings = false): UpdateResult
    {
        $result = $this->installer->update($name, $force, fn (): bool => $acceptWarnings);

        SkillUpdated::dispatch($result->skill, $result->previousVersion, $result->version);

        return $result;
    }

    public function trust(string $name, bool $trusted): void
    {
        $this->lock->setTrust($name, $trusted ? Trust::Trusted : Trust::Untrusted);

        SkillTrustChanged::dispatch($name, $trusted ? Trust::Trusted : Trust::Untrusted);
    }

    public function audit(string $name): ScanReport
    {
        return $this->scanner->scan($this->registry->resolve($name));
    }

    /**
     * Integrity report for skills and CLI tools: name => mismatches.
     *
     * @return array<string, array<int, string>>
     */
    public function verify(): array
    {
        $report = [];

        foreach (array_keys($this->lock->entries()) as $name) {
            $skill = $this->registry->has($name) ? $this->registry->resolve($name) : null;
            $report[$name] = $skill instanceof Skill ? $this->lock->mismatches($skill) : ['<missing from disk>'];
        }

        foreach ($this->cliTools->all() as $tool) {
            $report['cli:'.$tool->name] = $this->lock->cliToolMismatches($tool);
        }

        return $report;
    }
}
