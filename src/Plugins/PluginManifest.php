<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins;

use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\InvalidPluginManifestException;
use Illuminate\Support\Facades\File;

final class PluginManifest
{
    /**
     * Parse the plugin manifest from a directory. Accepts the toolbox
     * manifest (ai-plugin.json) and the Claude plugin format
     * (.claude-plugin/plugin.json).
     */
    public function parse(string $basePath): Plugin
    {
        $manifestFile = $this->manifestFile($basePath);

        $decoded = json_decode(File::get($manifestFile), true);

        if (! is_array($decoded)) {
            throw InvalidPluginManifestException::invalid($manifestFile, 'it must contain a JSON object');
        }

        $name = $decoded['name'] ?? null;

        if (! is_string($name) || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            throw InvalidPluginManifestException::invalid($manifestFile, 'the [name] field is required and must be kebab-case');
        }

        $version = $decoded['version'] ?? '0.0.0';
        $description = $decoded['description'] ?? '';

        return new Plugin(
            name: $name,
            version: is_string($version) ? $version : '0.0.0',
            description: is_string($description) ? $description : '',
            basePath: $basePath,
            skillsPath: $this->skillsPath($decoded),
            agents: $this->agents($decoded, $manifestFile),
            listeners: $this->listeners($decoded, $manifestFile),
        );
    }

    private function manifestFile(string $basePath): string
    {
        foreach (['ai-plugin.json', '.claude-plugin'.DIRECTORY_SEPARATOR.'plugin.json'] as $candidate) {
            if (File::isFile($basePath.DIRECTORY_SEPARATOR.$candidate)) {
                return $basePath.DIRECTORY_SEPARATOR.$candidate;
            }
        }

        throw InvalidPluginManifestException::notFound($basePath);
    }

    /**
     * The skills path accepts a string ("./skills") or a list (["./skills"]).
     *
     * @param  array<mixed>  $decoded
     */
    private function skillsPath(array $decoded): ?string
    {
        $skills = $decoded['skills'] ?? null;

        if (is_string($skills)) {
            return $this->normalizePath($skills);
        }

        if (is_array($skills)) {
            $first = $skills[0] ?? null;

            return is_string($first) ? $this->normalizePath($first) : null;
        }

        return null;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<string, string>
     */
    private function agents(array $decoded, string $manifestFile): array
    {
        $agents = $decoded['agents'] ?? [];

        if (! is_array($agents)) {
            throw InvalidPluginManifestException::invalid($manifestFile, 'the [agents] field must be a map of name to class');
        }

        $normalized = [];

        foreach ($agents as $name => $class) {
            if (! is_string($name) || ! is_string($class)) {
                throw InvalidPluginManifestException::invalid($manifestFile, 'every [agents] entry must map a name to a class name');
            }

            $normalized[$name] = $class;
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $decoded
     * @return array<string, array<int, string>>
     */
    private function listeners(array $decoded, string $manifestFile): array
    {
        $listeners = $decoded['listeners'] ?? [];

        if (! is_array($listeners)) {
            throw InvalidPluginManifestException::invalid($manifestFile, 'the [listeners] field must be a map of event names to listener classes');
        }

        $normalized = [];

        foreach ($listeners as $event => $classes) {
            if (! is_string($event) || ! is_array($classes)) {
                throw InvalidPluginManifestException::invalid($manifestFile, 'every [listeners] entry must map an event name to a list of classes');
            }

            $normalized[$event] = array_values(array_filter($classes, is_string(...)));
        }

        return $normalized;
    }

    private function normalizePath(string $path): string
    {
        return mb_trim(mb_ltrim($path, './'), '/');
    }
}
