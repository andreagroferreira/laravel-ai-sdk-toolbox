<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('List the installed plugins')]
#[Signature('ai:plugin-list')]
final class PluginListCommand extends Command
{
    public function handle(PluginManager $manager): int
    {
        $entries = app(\AndreAgroFerreira\AiSdkToolbox\Plugins\PluginRegistry::class)->entries();

        if ($entries === []) {
            $this->components->info('No plugins installed.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($entries as $name => $entry) {
            $rows[] = [
                $name,
                $entry['version'],
                $entry['enabled'] ? 'enabled' : 'disabled',
                $entry['source'],
            ];
        }

        $this->table(['Name', 'Version', 'State', 'Source'], $rows);

        return self::SUCCESS;
    }
}
