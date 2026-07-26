<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\InvalidPluginManifestException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginInstallException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Install a plugin from a local path, a git URL or a GitHub shorthand')]
#[Signature('ai:plugin-install
    {source : Local path, git URL or GitHub shorthand (vendor/repo)}
    {--path= : Subdirectory of the source containing the plugin manifest}
    {--disabled : Install without enabling}')]
final class PluginInstallCommand extends Command
{
    public function handle(PluginManager $manager): int
    {
        $source = $this->argument('source');

        if (! is_string($source)) {
            $this->components->error('The source must be a string.');

            return self::FAILURE;
        }

        $path = $this->option('path');

        try {
            $plugin = $manager->install(
                source: $source,
                path: is_string($path) ? $path : null,
                enabled: ! $this->option('disabled'),
            );
        } catch (PluginInstallException|InvalidPluginManifestException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Plugin [%s] v%s installed%s.',
            $plugin->name,
            $plugin->version,
            $this->option('disabled') ? ' (disabled)' : ' and enabled',
        ));

        return self::SUCCESS;
    }
}
