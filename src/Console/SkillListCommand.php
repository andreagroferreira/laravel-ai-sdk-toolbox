<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Description('List all registered skills')]
#[Signature('ai:skill-list')]
final class SkillListCommand extends Command
{
    public function handle(SkillRegistry $registry): int
    {
        $rows = [];

        foreach (array_keys($registry->sources() === [] ? [] : $this->index($registry)) as $name) {
            try {
                $skill = $registry->resolve($name);
                $rows[] = [$skill->name, $skill->source, $skill->trust->value, $skill->hasProvider() ? 'yes' : 'no', (string) count($skill->scripts), $skill->description];
            } catch (Throwable $throwable) {
                $rows[] = [$name, '?', '?', '?', '?', 'INVALID: '.$throwable->getMessage()];
            }
        }

        if ($rows === []) {
            $this->components->info('No skills registered.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Source', 'Trust', 'Provider', 'Scripts', 'Description'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function index(SkillRegistry $registry): array
    {
        $names = [];

        foreach ($registry->sources() as $source => $path) {
            foreach (glob($path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'SKILL.md') ?: [] as $file) {
                $names[basename(dirname($file))] = true;
            }
        }

        return $names;
    }
}
