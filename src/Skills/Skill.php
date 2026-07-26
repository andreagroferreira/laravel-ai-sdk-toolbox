<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

final readonly class Skill
{
    /**
     * @param  array<int, string>  $scripts
     * @param  array<string, mixed>  $frontmatter
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $instructions,
        public string $basePath,
        public string $source,
        public Trust $trust,
        public ?string $provider = null,
        public array $scripts = [],
        public array $frontmatter = [],
    ) {}

    public function hasProvider(): bool
    {
        return $this->provider !== null;
    }

    public function withTrust(Trust $trust): self
    {
        return new self(
            name: $this->name,
            description: $this->description,
            instructions: $this->instructions,
            basePath: $this->basePath,
            source: $this->source,
            trust: $trust,
            provider: $this->provider,
            scripts: $this->scripts,
            frontmatter: $this->frontmatter,
        );
    }

    public function hasScripts(): bool
    {
        return $this->scripts !== [];
    }

    public function scriptsPath(): string
    {
        return $this->basePath.DIRECTORY_SEPARATOR.'scripts';
    }
}
