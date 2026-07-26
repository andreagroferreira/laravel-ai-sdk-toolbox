<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Show the details of a skill')]
#[Signature('ai:skill-show {name : The skill name}')]
final class SkillShowCommand extends Command
{
    public function handle(SkillRegistry $registry): int
    {
        $name = $this->argument('name');

        if (! is_string($name)) {
            $this->components->error('The skill name must be a string.');

            return self::FAILURE;
        }

        try {
            $skill = $registry->resolve($name);
        } catch (SkillNotFoundException $skillNotFoundException) {
            $this->components->error($skillNotFoundException->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Name', $skill->name);
        $this->components->twoColumnDetail('Description', $skill->description);
        $this->components->twoColumnDetail('Source', $skill->source);
        $this->components->twoColumnDetail('Trust', $skill->trust->value);
        $this->components->twoColumnDetail('Path', $skill->basePath);
        $this->components->twoColumnDetail('Provider', $skill->provider ?? 'none');
        $this->components->twoColumnDetail('Scripts', $skill->scripts === [] ? 'none' : implode(', ', $skill->scripts));

        return self::SUCCESS;
    }
}
