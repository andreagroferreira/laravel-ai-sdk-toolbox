<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\CliTools;

use AndreAgroFerreira\AiSdkToolbox\CliTools\Exceptions\CliToolNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use Illuminate\Support\Collection;

final class CliToolRegistry
{
    public function __construct(
        private readonly SkillLock $lock,
    ) {}

    public function has(string $name): bool
    {
        return isset($this->lock->cliToolEntries()[$name]);
    }

    public function resolve(string $name): CliTool
    {
        $entry = $this->lock->cliToolEntries()[$name] ?? throw CliToolNotFoundException::named($name);

        return new CliTool(
            name: $name,
            path: $entry['path'],
            runtime: $entry['runtime'],
            source: $entry['source'],
            trust: Trust::tryFrom($entry['trust']) ?? Trust::Untrusted,
            env: $entry['env'],
            version: $entry['version'],
        );
    }

    /**
     * @return Collection<int, CliTool>
     */
    public function all(): Collection
    {
        return new Collection(array_map(
            fn (string $name): CliTool => $this->resolve($name),
            array_keys($this->lock->cliToolEntries()),
        ));
    }
}
