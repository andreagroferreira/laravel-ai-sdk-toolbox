<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins;

use AndreAgroFerreira\AiSdkToolbox\Events\PluginDisabled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginEnabled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginRemoved;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\InvalidPluginManifestException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginInstallException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginNotFoundException;
use Composer\InstalledVersions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

final class PluginManager
{
    public function __construct(
        private readonly PluginManifest $manifest,
        private readonly PluginRegistry $registry,
        private readonly Repository $config,
    ) {}

    /**
     * Install a plugin from a local path, a git URL or a GitHub shorthand.
     *
     * @throws InvalidPluginManifestException
     * @throws PluginInstallException
     */
    public function install(string $source, ?string $path = null, bool $enabled = true): Plugin
    {
        [$root, $cleanup] = $this->resolveSourceRoot($source, $path);

        try {
            $plugin = $this->manifest->parse($root);

            if ($this->registry->has($plugin->name)) {
                throw PluginInstallException::alreadyInstalled($plugin->name);
            }

            $destination = $this->pluginsPath().DIRECTORY_SEPARATOR.$plugin->name;
            File::ensureDirectoryExists($this->pluginsPath());
            File::copyDirectory($root, $destination);

            $plugin = $this->manifest->parse($destination);

            $this->registry->put($plugin, $source, $enabled);
            $this->registry->bootEnabled();

            PluginInstalled::dispatch($plugin, $source);

            return $plugin;
        } finally {
            $cleanup();
        }
    }

    /**
     * Register plugins shipped by Composer packages via extra.laravel-ai.plugin.
     *
     * @return array<int, Plugin>
     */
    public function installComposerPlugins(): array
    {
        $installed = [];

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
            $pluginPath = $laravelAi['plugin'] ?? null;

            if (! is_string($pluginPath)) {
                continue;
            }

            $root = $installPath.DIRECTORY_SEPARATOR.$pluginPath;

            try {
                $plugin = $this->manifest->parse($root);
            } catch (InvalidPluginManifestException) {
                continue;
            }

            if ($this->registry->has($plugin->name)) {
                continue;
            }

            $this->registry->put($plugin, 'composer:'.$package, true);
            $installed[] = $plugin;
        }

        return $installed;
    }

    public function remove(string $name): void
    {
        $entry = $this->registry->get($name) ?? throw PluginNotFoundException::named($name);

        $pluginsPath = realpath($this->pluginsPath());
        $entryPath = realpath($entry['path']);

        if ($pluginsPath !== false && $entryPath !== false && str_starts_with($entryPath, $pluginsPath.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($entryPath);
        }

        $this->registry->remove($name);

        PluginRemoved::dispatch($name);
    }

    public function enable(string $name): void
    {
        if (! $this->registry->has($name)) {
            throw PluginNotFoundException::named($name);
        }

        $this->registry->setEnabled($name, true);
        $this->registry->bootEnabled();

        PluginEnabled::dispatch($name);
    }

    public function disable(string $name): void
    {
        if (! $this->registry->has($name)) {
            throw PluginNotFoundException::named($name);
        }

        $this->registry->setEnabled($name, false);

        PluginDisabled::dispatch($name);
    }

    public function find(string $name): Plugin
    {
        $entry = $this->registry->get($name) ?? throw PluginNotFoundException::named($name);

        return $this->manifest->parse($entry['path']);
    }

    public function pluginsPath(): string
    {
        $path = $this->config->get('ai-sdk-toolbox.plugins.path');

        return is_string($path) ? $path : storage_path('app/ai/plugins');
    }

    private static function normalizeSource(string $source): string
    {
        if (preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#i', $source) === 1) {
            return sprintf('https://github.com/%s.git', $source);
        }

        return $source;
    }

    /**
     * @return array{0: string, 1: callable}
     */
    private function resolveSourceRoot(string $source, ?string $path): array
    {
        $source = self::normalizeSource($source);

        if (File::isDirectory($source)) {
            $root = $path === null ? $source : $source.DIRECTORY_SEPARATOR.$path;

            return [$root, static function (): void {}];
        }

        if (preg_match('#^(https?://|git@)#', $source) === 1) {
            $temporary = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ai-plugin-'.Str::random(8);

            $result = Process::timeout(120)->run(['git', 'clone', '--depth', '1', $source, $temporary]);

            if ($result->failed()) {
                throw PluginInstallException::cloneFailed($source, $result->errorOutput());
            }

            $root = $path === null ? $temporary : $temporary.DIRECTORY_SEPARATOR.$path;

            return [$root, static function () use ($temporary): void {
                File::deleteDirectory($temporary);
            }];
        }

        throw PluginInstallException::unsupportedSource($source);
    }
}
