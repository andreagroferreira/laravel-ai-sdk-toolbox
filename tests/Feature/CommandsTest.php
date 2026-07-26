<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Assert;

function testInstalledPath(): string
{
    return sys_get_temp_dir().'/ai-toolbox-test/installed';
}

function testLocalPath(): string
{
    return sys_get_temp_dir().'/ai-toolbox-test/local';
}

beforeEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/ai-toolbox-test');
    File::ensureDirectoryExists(testInstalledPath());
    File::ensureDirectoryExists(testLocalPath());

    config()->set('ai-sdk-toolbox.skills.paths', [
        'local' => testLocalPath(),
        'installed' => testInstalledPath(),
    ]);

    app()->instance(SkillLock::class, new SkillLock(sys_get_temp_dir().'/ai-toolbox-test/ai-skills.lock'));
});

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/ai-toolbox-test');
});

it('scaffolds a new local skill', function (): void {
    expect(Artisan::call('ai:skill', ['name' => 'my-skill']))->toBe(0);

    $contents = File::get(testLocalPath().'/my-skill/SKILL.md');

    expect($contents)->toContain('name: my-skill')
        ->and($contents)->toContain('description:');
});

it('rejects invalid skill names on scaffold', function (): void {
    expect(Artisan::call('ai:skill', ['name' => 'Invalid_Name']))->toBe(1);
});

it('installs a clean skill from a local path', function (): void {
    $source = __DIR__.'/../Fixtures/skills/tone-of-voice';

    expect(Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]))->toBe(0)
        ->and(File::isFile(testInstalledPath().'/tone-of-voice/SKILL.md'))->toBeTrue()
        ->and(app(SkillLock::class)->has('tone-of-voice'))->toBeTrue()
        ->and(app(SkillRegistry::class)->resolve('tone-of-voice')->trust)->toBe(Trust::Untrusted);
});

it('refuses to install a blocked skill without force', function (): void {
    $source = __DIR__.'/../Fixtures/skills/evil-php';

    expect(Artisan::call('ai:skill-install', ['source' => $source]))->toBe(1)
        ->and(File::isDirectory(testInstalledPath().'/evil-php'))->toBeFalse();
});

it('installs a blocked skill with force', function (): void {
    $source = __DIR__.'/../Fixtures/skills/evil-php';

    expect(Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]))->toBe(0)
        ->and(File::isDirectory(testInstalledPath().'/evil-php'))->toBeTrue();
});

it('refuses to install the same skill twice', function (): void {
    $source = __DIR__.'/../Fixtures/skills/tone-of-voice';

    expect(Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]))->toBe(0);
    expect(Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]))->toBe(1);
});

it('installs a skill from a git repository', function (): void {
    $repo = sys_get_temp_dir().'/ai-toolbox-test/skill-repo';
    File::ensureDirectoryExists($repo.'/git-skill');
    File::put($repo.'/git-skill/SKILL.md', "---\nname: git-skill\ndescription: A skill from git.\n---\n\n# Git Skill\n");

    Process::run(['git', 'init', '-b', 'main', $repo]);
    Process::run(['git', '-C', $repo, 'add', '-A']);
    Process::run(['git', '-C', $repo, '-c', 'user.email=test@example.com', '-c', 'user.name=Test', 'commit', '-m', 'chore: skill repo']);

    expect(Artisan::call('ai:skill-install', ['source' => $repo, '--force' => true]))->toBe(0);

    $entry = app(SkillLock::class)->get('git-skill');

    if ($entry === null) {
        Assert::fail('Expected the skill to be locked.');
    }

    expect($entry['version'])->not->toBeNull()
        ->and(File::isFile(testInstalledPath().'/git-skill/SKILL.md'))->toBeTrue();
});

it('removes an installed skill and its lock entry', function (): void {
    $source = __DIR__.'/../Fixtures/skills/tone-of-voice';

    Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]);

    expect(Artisan::call('ai:skill-remove', ['name' => 'tone-of-voice']))->toBe(0)
        ->and(File::isDirectory(testInstalledPath().'/tone-of-voice'))->toBeFalse()
        ->and(app(SkillLock::class)->has('tone-of-voice'))->toBeFalse();
});

it('promotes and demotes skill trust', function (): void {
    $source = __DIR__.'/../Fixtures/skills/tone-of-voice';

    Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]);

    expect(Artisan::call('ai:skill-trust', ['name' => 'tone-of-voice']))->toBe(0)
        ->and(app(SkillRegistry::class)->resolve('tone-of-voice')->trust)->toBe(Trust::Trusted);

    app()->forgetInstance(SkillRegistry::class);

    expect(Artisan::call('ai:skill-trust', ['name' => 'tone-of-voice', '--untrust' => true]))->toBe(0)
        ->and(app(SkillRegistry::class)->resolve('tone-of-voice')->trust)->toBe(Trust::Untrusted);
});

it('fails to change trust of skills that are not locked', function (): void {
    expect(Artisan::call('ai:skill-trust', ['name' => 'missing']))->toBe(1);
});

it('verifies integrity and reports tampering', function (): void {
    $source = __DIR__.'/../Fixtures/skills/tone-of-voice';

    Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]);

    expect(Artisan::call('ai:skill-verify'))->toBe(0);

    File::put(testInstalledPath().'/tone-of-voice/SKILL.md', File::get(testInstalledPath().'/tone-of-voice/SKILL.md').'tampered');

    expect(Artisan::call('ai:skill-verify'))->toBe(1);
});

it('lists and shows skills', function (): void {
    $source = __DIR__.'/../Fixtures/skills/tone-of-voice';

    Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]);

    expect(Artisan::call('ai:skill-list'))->toBe(0)
        ->and(Artisan::call('ai:skill-show', ['name' => 'tone-of-voice']))->toBe(0)
        ->and(Artisan::call('ai:skill-show', ['name' => 'missing']))->toBe(1);
});

it('audits a skill and prints the report', function (): void {
    $source = __DIR__.'/../Fixtures/skills/evil-injection';

    Artisan::call('ai:skill-install', ['source' => $source, '--force' => true]);

    expect(Artisan::call('ai:skill-audit', ['name' => 'evil-injection']))->toBe(0)
        ->and(Artisan::call('ai:skill-audit', ['name' => 'missing']))->toBe(1);
});
