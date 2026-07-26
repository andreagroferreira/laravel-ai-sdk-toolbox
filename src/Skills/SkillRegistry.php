<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;

final class SkillRegistry
{
    /**
     * @var array<string, string>
     */
    private array $runtimePaths = [];

    /**
     * @var array<string, array{source: string, file: string}>|null
     */
    private ?array $index = null;

    /**
     * @var array<string, Skill>
     */
    private array $resolved = [];

    public function __construct(
        private readonly SkillParser $parser,
        private readonly Repository $config,
    ) {}

    /**
     * Register an additional skills path at runtime (e.g. from a plugin).
     */
    public function addPath(string $source, string $path): void
    {
        $this->runtimePaths[$source] = $path;
        $this->index = null;
    }

    /**
     * @return array<string, string>
     */
    public function sources(): array
    {
        /** @var array<string, string> $configPaths */
        $configPaths = $this->config->get('ai-sdk-toolbox.skills.paths', []);

        return $configPaths + $this->runtimePaths + $this->composerPaths();
    }

    public function has(string $name): bool
    {
        return isset($this->index()[$name]);
    }

    public function resolve(string $name): Skill
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        $entry = $this->index()[$name] ?? throw SkillNotFoundException::named($name);

        $skill = $this->parser->parse(
            $entry['file'],
            $entry['source'],
            $this->trustFor($entry['source']),
        );

        $lockedTrust = $this->lockedTrust($name);

        return $this->resolved[$name] = $lockedTrust === null ? $skill : $skill->withTrust($lockedTrust);
    }

    /**
     * Resolve several skills by name, preserving the given order.
     *
     * @param  iterable<int, string|Skill>  $skills
     * @return Collection<int, Skill>
     */
    public function resolveMany(iterable $skills): Collection
    {
        $resolved = [];

        foreach ($skills as $skill) {
            $resolved[] = $skill instanceof Skill ? $skill : $this->resolve($skill);
        }

        return new Collection($resolved);
    }

    /**
     * Every resolvable skill. Skills that fail to parse are skipped.
     *
     * @return Collection<int, Skill>
     */
    public function all(): Collection
    {
        $skills = [];

        foreach (array_keys($this->index()) as $name) {
            try {
                $skills[] = $this->resolve($name);
            } catch (Exceptions\InvalidSkillException) {
                continue;
            }
        }

        return new Collection($skills);
    }

    public function trustFor(string $source): Trust
    {
        /** @var array<string, string> $sources */
        $sources = $this->config->get('ai-sdk-toolbox.skills.trust.sources', []);

        $level = $sources[$source] ?? $this->config->get('ai-sdk-toolbox.skills.trust.default', 'untrusted');

        return $level === 'trusted' ? Trust::Trusted : Trust::Untrusted;
    }

    private function lockedTrust(string $name): ?Trust
    {
        $locked = app(SkillLock::class)->get($name);
        $trust = $locked['trust'] ?? null;

        return is_string($trust) ? Trust::tryFrom($trust) : null;
    }

    /**
     * @return array<string, array{source: string, file: string}>
     */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $index = [];

        foreach ($this->sources() as $source => $path) {
            if (! is_dir($path)) {
                continue;
            }

            foreach (glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'SKILL.md') ?: [] as $file) {
                $name = basename(dirname($file));
                $index[$name] ??= ['source' => $source, 'file' => $file];
            }
        }

        ksort($index);

        return $this->index = $index;
    }

    /**
     * Skills paths declared by installed Composer packages via extra.laravel-ai.skills.
     *
     * @return array<string, string>
     */
    private function composerPaths(): array
    {
        $paths = [];

        foreach (InstalledVersions::getInstalledPackages() as $package) {
            $installPath = InstalledVersions::getInstallPath($package);

            if (! is_string($installPath)) {
                continue;
            }

            $manifest = $installPath.DIRECTORY_SEPARATOR.'composer.json';

            if (! is_file($manifest)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($manifest), true);

            if (! is_array($decoded)) {
                continue;
            }

            $extra = isset($decoded['extra']) && is_array($decoded['extra']) ? $decoded['extra'] : [];
            $laravelAi = isset($extra['laravel-ai']) && is_array($extra['laravel-ai']) ? $extra['laravel-ai'] : [];
            $skills = $laravelAi['skills'] ?? null;

            if (! is_string($skills)) {
                continue;
            }

            $paths['composer:'.$package] = $installPath.DIRECTORY_SEPARATOR.$skills;
        }

        return $paths;
    }
}
