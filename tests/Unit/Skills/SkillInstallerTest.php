<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillInstallException;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use Illuminate\Support\Facades\File;

it('expands GitHub shorthands into git URLs', function (): void {
    expect(SkillInstaller::normalizeSource('coreyhaines31/marketingskills'))
        ->toBe('https://github.com/coreyhaines31/marketingskills.git')
        ->and(SkillInstaller::normalizeSource('https://github.com/x/y.git'))
        ->toBe('https://github.com/x/y.git')
        ->and(SkillInstaller::normalizeSource('/local/path'))
        ->toBe('/local/path')
        ->and(SkillInstaller::normalizeSource('too/many/parts'))
        ->toBe('too/many/parts');
});

it('lists candidate skills in the ambiguous directory error', function (): void {
    $installer = app(SkillInstaller::class);

    try {
        $installer->install(__DIR__.'/../../Fixtures/skills');
    } catch (SkillInstallException $skillInstallException) {
        expect($skillInstallException->reason)->toBe('ambiguous')
            ->and($skillInstallException->getMessage())->toContain('tone-of-voice')
            ->and($skillInstallException->getMessage())->toContain('remember')
            ->and($skillInstallException->getMessage())->toContain('--all');

        return;
    }

    PHPUnit\Framework\Assert::fail('Expected an ambiguous SkillInstallException.');
});

it('installs many skills and reports skips and failures', function (): void {
    config()->set('ai-sdk-toolbox.skills.paths.installed', sys_get_temp_dir().'/ai-install-many/installed');
    File::deleteDirectory(sys_get_temp_dir().'/ai-install-many');

    $installer = app(SkillInstaller::class);
    $result = $installer->installMany(__DIR__.'/../../Fixtures/skills', force: true);

    $names = array_map(fn ($result): string => $result->skill->name, $result->installed);

    expect($names)->toContain('tone-of-voice')
        ->and($names)->toContain('remember')
        ->and($names)->toContain('backup')
        ->and($names)->toContain('evil-php')
        ->and(array_keys($result->failed))->toContain('broken-no-frontmatter')
        ->and(array_keys($result->failed))->toContain('broken-no-description')
        ->and(array_keys($result->failed))->toContain('broken-name-mismatch');

    $secondRun = $installer->installMany(__DIR__.'/../../Fixtures/skills', force: true);

    expect($secondRun->installed)->toBe([])
        ->and($secondRun->skipped)->toContain('tone-of-voice');

    File::deleteDirectory(sys_get_temp_dir().'/ai-install-many');
});

it('refuses blocked skills in bulk installs without force', function (): void {
    config()->set('ai-sdk-toolbox.skills.paths.installed', sys_get_temp_dir().'/ai-install-many-2/installed');
    File::deleteDirectory(sys_get_temp_dir().'/ai-install-many-2');

    $result = app(SkillInstaller::class)->installMany(__DIR__.'/../../Fixtures/skills');

    $names = array_map(fn ($result): string => $result->skill->name, $result->installed);

    expect($names)->not->toContain('evil-php')
        ->and($result->failed['evil-php'])->toContain('blocked by the security scan');

    File::deleteDirectory(sys_get_temp_dir().'/ai-install-many-2');
});
