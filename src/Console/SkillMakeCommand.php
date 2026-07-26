<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\File;

#[Description('Create a new local skill scaffold')]
#[Signature('ai:skill {name : The skill name in kebab-case}')]
final class SkillMakeCommand extends Command
{
    public function handle(Repository $config): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The skill name must be a string.');

            return self::FAILURE;
        }

        if (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $name) !== 1) {
            $this->components->error(sprintf('The skill name [%s] is invalid. Use kebab-case, e.g. tone-of-voice.', $name));

            return self::FAILURE;
        }

        /** @var array<string, string> $paths */
        $paths = $config->get('ai-sdk-toolbox.skills.paths', []);
        $directory = ($paths['local'] ?? resource_path('ai/skills')).DIRECTORY_SEPARATOR.$name;

        if (File::isDirectory($directory)) {
            $this->components->error(sprintf('The skill [%s] already exists at [%s].', $name, $directory));

            return self::FAILURE;
        }

        File::ensureDirectoryExists($directory);
        File::put($directory.DIRECTORY_SEPARATOR.'SKILL.md', <<<MARKDOWN
        ---
        name: {$name}
        description: TODO — describe what this skill does and when to use it.
        ---

        # {$name}

        Write the skill instructions here. This markdown is injected into the
        agent instructions when the skill is applied.
        MARKDOWN.PHP_EOL);

        $this->components->info(sprintf('Skill [%s] created at [%s].', $name, $directory));

        return self::SUCCESS;
    }
}
