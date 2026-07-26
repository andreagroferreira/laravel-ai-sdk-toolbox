<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Disable an installed plugin')]
#[Signature('ai:plugin-disable {name : The plugin name}')]
final class PluginDisableCommand extends Command
{
    public function handle(PluginManager $manager): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The plugin name must be a string.');

            return self::FAILURE;
        }

        try {
            $manager->disable($name);
        } catch (PluginNotFoundException $pluginNotFoundException) {
            $this->components->error($pluginNotFoundException->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Plugin [%s] disabled.', $name));

        return self::SUCCESS;
    }
}
