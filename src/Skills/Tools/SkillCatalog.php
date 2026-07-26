<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Tools;

use AndreAgroFerreira\AiSdkToolbox\Skills\Skill;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class SkillCatalog implements Tool
{
    /**
     * @param  array<int, string>  $skills
     */
    public function __construct(
        private readonly array $skills = [],
    ) {}

    public function description(): string
    {
        return 'List the skills available to this agent, with their description, trust level, scripts and CLI tools.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): string
    {
        $skills = $this->registry()->resolveMany($this->skills);

        if ($skills->isEmpty()) {
            return 'This agent has no skills applied.';
        }

        return $skills
            ->map(fn (Skill $skill): string => sprintf(
                '- %s (trust: %s)%s%s — %s',
                $skill->name,
                $skill->trust->value,
                $skill->hasProvider() ? ', php tools' : '',
                $skill->hasScripts() ? sprintf(', scripts: %s', implode(', ', $skill->scripts)) : '',
                $skill->description,
            ))
            ->implode(PHP_EOL);
    }

    private function registry(): SkillRegistry
    {
        return app(SkillRegistry::class);
    }
}
