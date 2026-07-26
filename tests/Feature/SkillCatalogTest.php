<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Tools\SkillCatalog;
use AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Agents\SkilledAgent;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths', ['local' => __DIR__.'/../Fixtures/skills']);
    config()->set('ai-sdk-toolbox.skills.trust.sources', ['local' => 'trusted']);
});

it('lists the agent skills with trust and capabilities', function (): void {
    $result = (new SkillCatalog(['tone-of-voice', 'backup']))->handle(new Request([]));

    expect((string) $result)
        ->toContain('tone-of-voice (trust: trusted)')
        ->toContain('Apply the company tone of voice')
        ->and((string) $result)->toContain('backup (trust: trusted)')
        ->and((string) $result)->toContain('scripts: backup.py');
});

it('reports when the agent has no skills', function (): void {
    $result = (new SkillCatalog([]))->handle(new Request([]));

    expect((string) $result)->toContain('no skills');
});

it('is added automatically to agents with skills', function (): void {
    $tools = (new SkilledAgent)->tools();
    $tools = is_array($tools) ? array_values($tools) : array_values(iterator_to_array($tools));

    expect(collect($tools)->contains(fn ($tool): bool => $tool instanceof SkillCatalog))->toBeTrue();
});
