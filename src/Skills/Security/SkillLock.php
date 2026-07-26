<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Security;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class SkillLock
{
    public function __construct(
        private readonly string $path,
    ) {}

    public static function atDefaultLocation(): self
    {
        return new self(base_path('ai-skills.lock'));
    }

    public function has(string $name): bool
    {
        return isset($this->entries()[$name]);
    }

    /**
     * @return array{source: string, version: string|null, trust: string|null, path?: string, installed_at: string, files: array<string, string>}|null
     */
    public function get(string $name): ?array
    {
        return $this->entries()[$name] ?? null;
    }

    public function setTrust(string $name, Trust $trust): void
    {
        $entries = $this->entries();

        if (! isset($entries[$name])) {
            return;
        }

        $entries[$name]['trust'] = $trust->value;

        $this->persist($entries, $this->cliToolEntries());
    }

    /**
     * @return array<string, array{source: string, version: string|null, trust: string|null, path?: string, installed_at: string, files: array<string, string>}>
     */
    public function entries(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        if (! is_array($decoded) || ! isset($decoded['skills']) || ! is_array($decoded['skills'])) {
            return [];
        }

        /** @var array<string, array{source: string, version: string|null, trust: string|null, path?: string, installed_at: string, files: array<string, string>}> */
        return $decoded['skills'];
    }

    public function put(Skill $skill, ?string $version = null, ?Trust $trust = null): void
    {
        $entries = $this->entries();
        $entries[$skill->name] = [
            'source' => $skill->source,
            'version' => $version,
            'trust' => ($trust ?? $skill->trust)->value,
            'path' => $skill->basePath,
            'installed_at' => date(DATE_ATOM),
            'files' => $this->hashes($skill->basePath),
        ];

        ksort($entries);

        $this->persist($entries, $this->cliToolEntries());
    }

    public function remove(string $name): void
    {
        $entries = $this->entries();
        unset($entries[$name]);

        $this->persist($entries, $this->cliToolEntries());
    }

    /**
     * @return array<string, array{path: string, runtime: string, source: string, trust: string, env: array<int, string>, version: string|null, installed_at: string, hash: string}>
     */
    public function cliToolEntries(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        if (! is_array($decoded) || ! isset($decoded['cli_tools']) || ! is_array($decoded['cli_tools'])) {
            return [];
        }

        /** @var array<string, array{path: string, runtime: string, source: string, trust: string, env: array<int, string>, version: string|null, installed_at: string, hash: string}> */
        return $decoded['cli_tools'];
    }

    public function putCliTool(CliTool $tool, string $hash): void
    {
        $tools = $this->cliToolEntries();
        $tools[$tool->name] = [
            'path' => $tool->path,
            'runtime' => $tool->runtime,
            'source' => $tool->source,
            'trust' => $tool->trust->value,
            'env' => $tool->env,
            'version' => $tool->version,
            'installed_at' => date(DATE_ATOM),
            'hash' => $hash,
        ];

        ksort($tools);

        $this->persist($this->entries(), $tools);
    }

    public function setCliToolTrust(string $name, Trust $trust): void
    {
        $tools = $this->cliToolEntries();

        if (! isset($tools[$name])) {
            return;
        }

        $tools[$name]['trust'] = $trust->value;

        $this->persist($this->entries(), $tools);
    }

    /**
     * @return array<int, string>
     */
    public function cliToolMismatches(CliTool $tool): array
    {
        $locked = $this->cliToolEntries()[$tool->name] ?? null;

        if ($locked === null) {
            return ['<not locked>'];
        }

        $current = is_file($tool->path) ? hash_file('sha256', $tool->path) : false;

        if ($current === false) {
            return [basename($tool->path).' (removed)'];
        }

        return $locked['hash'] === $current ? [] : [basename($tool->path).' (modified)'];
    }

    /**
     * Files whose current hash does not match the locked hash, plus files
     * that were added or removed since installation.
     *
     * @return array<int, string>
     */
    public function mismatches(Skill $skill): array
    {
        $locked = $this->get($skill->name);

        if ($locked === null) {
            return ['<not locked>'];
        }

        $basePath = isset($locked['path']) ? $locked['path'] : $skill->basePath;
        $current = $this->hashes($basePath);
        $mismatches = [];

        foreach ($current as $file => $hash) {
            if (! isset($locked['files'][$file])) {
                $mismatches[] = $file.' (added)';
            } elseif ($locked['files'][$file] !== $hash) {
                $mismatches[] = $file.' (modified)';
            }
        }

        foreach ($locked['files'] as $file => $hash) {
            if (! isset($current[$file])) {
                $mismatches[] = $file.' (removed)';
            }
        }

        return $mismatches;
    }

    /**
     * @param  array<string, array{source: string, version: string|null, trust: string|null, installed_at: string, files: array<string, string>}>  $entries
     * @param  array<string, array{path: string, runtime: string, source: string, trust: string, env: array<int, string>, version: string|null, installed_at: string, hash: string}>  $cliTools
     */
    private function persist(array $entries, array $cliTools): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode([
            'skills' => $entries,
            'cli_tools' => $cliTools,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    /**
     * @return array<string, string>
     */
    private function hashes(string $basePath): array
    {
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                $hash = hash_file('sha256', $file->getPathname());

                if ($hash !== false) {
                    $hashes[mb_ltrim(mb_substr($file->getPathname(), mb_strlen($basePath)), DIRECTORY_SEPARATOR)] = $hash;
                }
            }
        }

        ksort($hashes);

        return $hashes;
    }
}
