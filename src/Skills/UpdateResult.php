<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;

final readonly class UpdateResult
{
    /**
     * @param  array<int, \AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool>  $cliTools
     */
    public function __construct(
        public Skill $skill,
        public ScanReport $report,
        public ?string $previousVersion,
        public ?string $version,
        public array $cliTools = [],
    ) {}
}
