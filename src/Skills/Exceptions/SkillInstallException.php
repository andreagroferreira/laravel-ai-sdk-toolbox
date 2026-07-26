<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions;

use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use RuntimeException;

final class SkillInstallException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly string $reason,
    ) {
        parent::__construct($message);
    }

    public static function unsupportedSource(string $source): self
    {
        return new self(sprintf('The source [%s] is not supported. Use a local path, a git URL or a GitHub shorthand (vendor/repo).', $source), 'unsupported-source');
    }

    public static function skillFileNotFound(string $path): self
    {
        return new self(sprintf('No SKILL.md found in [%s].', $path), 'skill-not-found');
    }

    /**
     * @param  array<int, string>  $candidates
     */
    public static function ambiguousSkillDirectory(string $path, array $candidates): self
    {
        return new self(sprintf(
            'Several skills found in [%s]: %s. Use the --path option to pick one, or --all to install them all.',
            $path,
            implode(', ', $candidates),
        ), 'ambiguous');
    }

    public static function alreadyInstalled(Skill $skill): self
    {
        return new self(sprintf('The skill [%s] is already installed. Remove it first with ai:skill-remove.', $skill->name), 'already-installed');
    }

    public static function blocked(ScanReport $report): self
    {
        return new self(sprintf(
            'The skill [%s] was blocked by the security scan (%d blocking findings). Use --force to install it anyway.',
            $report->skill->name,
            $report->findings->count(),
        ), 'blocked');
    }

    public static function aborted(): self
    {
        return new self('Installation aborted.', 'aborted');
    }

    public static function notInstalled(string $name): self
    {
        return new self(sprintf('The skill [%s] is not installed, so it cannot be updated.', $name), 'not-installed');
    }

    public static function sourceNotResolvable(string $name, string $source): self
    {
        return new self(sprintf('The skill [%s] cannot be updated: the source [%s] no longer resolves.', $name, $source), 'source-not-resolvable');
    }

    public static function cloneFailed(string $source, string $output): self
    {
        return new self(sprintf('Failed to clone [%s]: %s', $source, mb_trim($output)), 'clone-failed');
    }
}
