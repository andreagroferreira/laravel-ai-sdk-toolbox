<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Agents;

use AndreAgroFerreira\AiSdkToolbox\Skills\Concerns\HasSkills;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

final class SkilledAgent implements Agent, HasMiddleware, HasTools
{
    use HasSkills;
    use Promptable;

    /**
     * @param  array<int, string>  $skillNames
     */
    public function __construct(
        private readonly array $skillNames = ['tone-of-voice', 'remember'],
    ) {}

    public function instructions(): string
    {
        return $this->withSkillInstructions('You are a helpful assistant.');
    }

    /**
     * @return array<int, string>
     */
    public function skills(): array
    {
        return $this->skillNames;
    }

    /**
     * @return iterable<\Laravel\Ai\Contracts\Tool|\Laravel\Ai\Providers\Tools\ProviderTool>
     */
    public function tools(): iterable
    {
        return $this->withSkillTools([]);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return $this->withSkillMiddleware([]);
    }
}
