<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths', [
        'local' => __DIR__.'/../../Fixtures/skills',
        'empty' => __DIR__.'/../../Fixtures/does-not-exist',
    ]);
});

it('resolves a skill by name', function (): void {
    $registry = app(SkillRegistry::class);

    expect($registry->has('tone-of-voice'))->toBeTrue()
        ->and($registry->resolve('tone-of-voice')->name)->toBe('tone-of-voice');
});

it('throws when the skill does not exist', function (): void {
    app(SkillRegistry::class)->resolve('missing-skill');
})->throws(SkillNotFoundException::class, 'missing-skill');

it('lists all skills with a valid SKILL.md index entry and skips missing paths', function (): void {
    $names = app(SkillRegistry::class)->sources();

    expect($names)->toHaveKey('local')
        ->and($names)->toHaveKey('empty');
});

it('resolves all skills and skips the broken ones on demand only', function (): void {
    $registry = app(SkillRegistry::class);

    expect($registry->has('broken-no-frontmatter'))->toBeTrue();
    expect(fn () => $registry->resolve('broken-no-frontmatter'))->toThrow(Exception::class);
});

it('assigns trust from the configuration', function (): void {
    config()->set('ai-sdk-toolbox.skills.trust.sources', ['local' => 'trusted']);

    $registry = app(SkillRegistry::class);

    expect($registry->trustFor('local'))->toBe(Trust::Trusted)
        ->and($registry->trustFor('composer:vendor/pkg'))->toBe(Trust::Untrusted);
});

it('honours a custom default trust level', function (): void {
    config()->set('ai-sdk-toolbox.skills.trust.default', 'trusted');

    expect(app(SkillRegistry::class)->trustFor('anything'))->toBe(Trust::Trusted);
});

it('resolves many skills preserving order', function (): void {
    $skills = app(SkillRegistry::class)->resolveMany(['remember', 'tone-of-voice']);

    expect($skills->map->name->all())->toBe(['remember', 'tone-of-voice']);
});

it('registers additional paths at runtime', function (): void {
    $registry = app(SkillRegistry::class);
    $registry->addPath('plugin:memory-pack', __DIR__.'/../../Fixtures/skills');

    expect($registry->sources())->toHaveKey('plugin:memory-pack');
});
