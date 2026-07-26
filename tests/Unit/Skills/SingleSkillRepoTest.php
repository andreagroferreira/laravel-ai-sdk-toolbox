<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\InvalidSkillException;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths.installed', sys_get_temp_dir().'/ai-single-skill/installed');
    File::deleteDirectory(sys_get_temp_dir().'/ai-single-skill');
});

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/ai-single-skill');
});

it('installs a skill living at the root of a repository', function (): void {
    $result = app(SkillInstaller::class)->install(__DIR__.'/../../Fixtures/single-skill-repo', force: true);

    expect($result->skill->name)->toBe('stop-slop')
        ->and($result->destination)->toEndWith('installed'.DIRECTORY_SEPARATOR.'stop-slop')
        ->and(File::isFile($result->destination.'/SKILL.md'))->toBeTrue();
});

it('installs many from a repository whose root is a single skill', function (): void {
    $result = app(SkillInstaller::class)->installMany(__DIR__.'/../../Fixtures/single-skill-repo', force: true);

    expect($result->installedCount())->toBe(1)
        ->and($result->installed[0]->skill->name)->toBe('stop-slop')
        ->and($result->failed)->toBe([]);
});

it('still rejects name mismatches when parsing strictly', function (): void {
    (new AndreAgroFerreira\AiSdkToolbox\Skills\SkillParser)->parse(__DIR__.'/../../Fixtures/single-skill-repo/SKILL.md');
})->throws(InvalidSkillException::class, 'must match');

it('rejects names that are not kebab-case even without directory strictness', function (): void {
    $directory = sys_get_temp_dir().'/ai-single-skill/odd-name';
    File::ensureDirectoryExists($directory);
    File::put($directory.'/SKILL.md', "---\nname: Not Kebab\ndescription: Invalid name.\n---\n\n# x\n");

    (new AndreAgroFerreira\AiSdkToolbox\Skills\SkillParser)->parse($directory.'/SKILL.md', strictDirectory: false);
})->throws(InvalidSkillException::class, 'kebab-case');
