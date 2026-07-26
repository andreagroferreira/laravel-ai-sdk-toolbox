<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillInstallException;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Remove an installed skill')]
#[Signature('ai:skill-remove {name : The skill name}')]
final class SkillRemoveCommand extends Command
{
    public function handle(SkillInstaller $installer): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The skill name must be a string.');

            return self::FAILURE;
        }

        try {
            $installer->uninstall($name);
        } catch (SkillInstallException $skillInstallException) {
            $this->components->error($skillInstallException->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Skill [%s] removed.', $name));

        return self::SUCCESS;
    }
}
