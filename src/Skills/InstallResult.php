<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport;

final readonly class InstallResult
{
    /**
     * @param  array<int, CliTool>  $cliTools
     */
    public function __construct(
        public Skill $skill,
        public ScanReport $report,
        public string $destination,
        public ?string $version,
        public array $cliTools = [],
    ) {}

    /**
     * @param  array<int, CliTool>  $cliTools
     */
    public function withCliTools(array $cliTools): self
    {
        return new self(
            skill: $this->skill,
            report: $this->report,
            destination: $this->destination,
            version: $this->version,
            cliTools: $cliTools,
        );
    }
}
