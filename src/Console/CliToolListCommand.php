<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool;
use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('List the registered CLI tools')]
#[Signature('ai:tool-list')]
final class CliToolListCommand extends Command
{
    public function handle(CliToolRegistry $registry): int
    {
        $tools = $registry->all();

        if ($tools->isEmpty()) {
            $this->components->info('No CLI tools registered. Install a source with a tools/clis directory.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Runtime', 'Source', 'Trust', 'Required environment'],
            $tools->map(fn (CliTool $tool): array => [
                $tool->name,
                $tool->runtime,
                $tool->source,
                $tool->trust->value,
                $tool->env === []
                    ? 'none'
                    : implode(', ', array_map(
                        fn (string $variable): string => $variable.(getenv($variable) !== false ? ' (set)' : ' (MISSING)'),
                        $tool->env,
                    )),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
