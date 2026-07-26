<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins;

use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Tracks installed plugins (ai-plugins.lock) and wires the enabled ones
 * into the application: skills paths, event listeners and named agents.
 */
final class PluginRegistry
{
    public function __construct(
        private readonly string $path,
        private readonly PluginManifest $manifest,
        private readonly SkillRegistry $skills,
        private readonly AgentRegistry $agents,
    ) {}

    public static function atDefaultLocation(SkillRegistry $skills, AgentRegistry $agents): self
    {
        return new self(base_path('ai-plugins.lock'), new PluginManifest, $skills, $agents);
    }

    public function has(string $name): bool
    {
        return isset($this->entries()[$name]);
    }

    /**
     * @return array{version: string, path: string, source: string, enabled: bool, installed_at: string}|null
     */
    public function get(string $name): ?array
    {
        return $this->entries()[$name] ?? null;
    }

    /**
     * @return array<string, array{version: string, path: string, source: string, enabled: bool, installed_at: string}>
     */
    public function entries(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        if (! is_array($decoded) || ! isset($decoded['plugins']) || ! is_array($decoded['plugins'])) {
            return [];
        }

        /** @var array<string, array{version: string, path: string, source: string, enabled: bool, installed_at: string}> */
        return $decoded['plugins'];
    }

    /**
     * @return array<string, array{version: string, path: string, source: string, enabled: bool, installed_at: string}>
     */
    public function enabled(): array
    {
        return array_filter($this->entries(), fn (array $entry): bool => $entry['enabled']);
    }

    public function put(Plugin $plugin, string $source, bool $enabled): void
    {
        $entries = $this->entries();
        $entries[$plugin->name] = [
            'version' => $plugin->version,
            'path' => $plugin->basePath,
            'source' => $source,
            'enabled' => $enabled,
            'installed_at' => date(DATE_ATOM),
        ];

        ksort($entries);

        $this->persist($entries);
    }

    public function setEnabled(string $name, bool $enabled): void
    {
        $entries = $this->entries();

        if (! isset($entries[$name])) {
            return;
        }

        $entries[$name]['enabled'] = $enabled;

        $this->persist($entries);
    }

    public function remove(string $name): void
    {
        $entries = $this->entries();
        unset($entries[$name]);

        $this->persist($entries);
    }

    /**
     * Wire every enabled plugin into the application. Called at boot:
     * failures in one plugin never block the others.
     */
    public function bootEnabled(): void
    {
        foreach ($this->enabled() as $name => $entry) {
            try {
                $plugin = $this->manifest->parse($entry['path']);

                if ($plugin->fullSkillsPath() !== null) {
                    $this->skills->addPath('plugin:'.$name, $plugin->fullSkillsPath());
                }

                foreach ($plugin->listeners as $event => $listeners) {
                    $eventClass = str_starts_with($event, '\\') || str_contains($event, '\\') ? $event : 'Laravel\\Ai\\Events\\'.$event;

                    foreach ($listeners as $listener) {
                        if (class_exists($eventClass) && class_exists($listener)) {
                            Event::listen($eventClass, $listener);
                        }
                    }
                }

                foreach ($plugin->agents as $agentName => $class) {
                    $this->agents->register($agentName, $class);
                }
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * @param  array<string, array{version: string, path: string, source: string, enabled: bool, installed_at: string}>  $entries
     */
    private function persist(array $entries): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, json_encode(['plugins' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
