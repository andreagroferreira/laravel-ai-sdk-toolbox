<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins;

final readonly class Plugin
{
    /**
     * @param  array<string, string>  $agents
     * @param  array<string, array<int, string>>  $listeners
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $description,
        public string $basePath,
        public ?string $skillsPath = null,
        public array $agents = [],
        public array $listeners = [],
    ) {}

    public function hasSkills(): bool
    {
        return $this->skillsPath !== null;
    }

    public function fullSkillsPath(): ?string
    {
        return $this->skillsPath === null
            ? null
            : $this->basePath.DIRECTORY_SEPARATOR.$this->skillsPath;
    }
}
