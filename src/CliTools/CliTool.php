<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\CliTools;

use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;

final readonly class CliTool
{
    /**
     * @param  array<int, string>  $env
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $runtime,
        public string $source,
        public Trust $trust,
        public array $env = [],
        public ?string $version = null,
    ) {}
}
