<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\InvalidSkillException;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillParser;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;

function fixtureSkillPath(string $name): string
{
    return __DIR__.'/../../Fixtures/skills/'.$name.'/SKILL.md';
}

it('parses a valid instruction-only skill', function (): void {
    $skill = (new SkillParser)->parse(fixtureSkillPath('tone-of-voice'));

    expect($skill->name)->toBe('tone-of-voice')
        ->and($skill->description)->toBe('Apply the company tone of voice to every answer.')
        ->and($skill->instructions)->toContain('# Tone of Voice')
        ->and($skill->instructions)->toContain('European Portuguese')
        ->and($skill->provider)->toBeNull()
        ->and($skill->scripts)->toBe([])
        ->and($skill->hasProvider())->toBeFalse()
        ->and($skill->hasScripts())->toBeFalse();
});

it('parses the provider and keeps unknown frontmatter keys', function (): void {
    $skill = (new SkillParser)->parse(fixtureSkillPath('remember'));

    expect($skill->provider)->toBe('AndreAgroFerreira\\AiSdkToolbox\\Tests\\Fixtures\\Classes\\Remember\\RememberProvider')
        ->and($skill->hasProvider())->toBeTrue()
        ->and($skill->frontmatter['custom-key'])->toBe('this key is ignored by Claude but kept in the frontmatter bag');
});

it('discovers scripts inside the skill', function (): void {
    $skill = (new SkillParser)->parse(fixtureSkillPath('backup'));

    expect($skill->scripts)->toBe(['backup.py', 'env_dump.py', 'helper.sh'])
        ->and($skill->hasScripts())->toBeTrue()
        ->and($skill->scriptsPath())->toEndWith('backup'.DIRECTORY_SEPARATOR.'scripts');
});

it('applies the given source and trust', function (): void {
    $skill = (new SkillParser)->parse(fixtureSkillPath('tone-of-voice'), 'composer:vendor/pkg', Trust::Untrusted);

    expect($skill->source)->toBe('composer:vendor/pkg')
        ->and($skill->trust)->toBe(Trust::Untrusted);
});

it('rejects a skill without frontmatter', function (): void {
    (new SkillParser)->parse(fixtureSkillPath('broken-no-frontmatter'));
})->throws(InvalidSkillException::class, 'no YAML frontmatter');

it('rejects a skill without description', function (): void {
    (new SkillParser)->parse(fixtureSkillPath('broken-no-description'));
})->throws(InvalidSkillException::class, 'description');

it('rejects a skill whose name does not match the directory', function (): void {
    (new SkillParser)->parse(fixtureSkillPath('broken-name-mismatch'));
})->throws(InvalidSkillException::class, 'must match');

it('rejects an unreadable file', function (): void {
    (new SkillParser)->parse(__DIR__.'/../../Fixtures/skills/missing/SKILL.md');
})->throws(InvalidSkillException::class);
