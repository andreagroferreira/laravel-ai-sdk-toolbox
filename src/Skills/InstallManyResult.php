<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;

final readonly class InstallManyResult
{
    /**
     * @param  array<int, InstallResult>  $installed
     * @param  array<int, string>  $skipped
     * @param  array<string, string>  $failed
     * @param  array<int, CliTool>  $cliTools
     */
    public function __construct(
        public array $installed,
        public array $skipped,
        public array $failed,
        public array $cliTools = [],
    ) {}

    public function installedCount(): int
    {
        return count($this->installed);
    }
}
