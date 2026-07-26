<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillUpdated;
use AndreAgroFerreira\AiSdkToolbox\Skills\Exceptions\SkillInstallException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths', [
        'local' => __DIR__.'/../Fixtures/skills',
        'installed' => sys_get_temp_dir().'/ai-update/installed',
    ]);

    File::deleteDirectory(sys_get_temp_dir().'/ai-update');
});

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/ai-update');
});

function gitSkillSource(): string
{
    $repo = sys_get_temp_dir().'/ai-update/repo';
    File::ensureDirectoryExists($repo.'/updatable-skill');
    File::put($repo.'/updatable-skill/SKILL.md', "---\nname: updatable-skill\ndescription: Version one.\n---\n\n# V1\n");

    Process::run(['git', 'init', '-b', 'main', $repo]);
    Process::run(['git', '-C', $repo, 'add', '-A']);
    Process::run(['git', '-C', $repo, '-c', 'user.email=test@example.com', '-c', 'user.name=Test', 'commit', '-m', 'v1']);

    return $repo;
}

it('updates an installed skill from its git source preserving trust', function (): void {
    Event::fake([SkillUpdated::class]);

    $repo = gitSkillSource();
    $installer = app(SkillInstaller::class);
    $installer->install($repo, force: true);

    app(SkillLock::class)->setTrust('updatable-skill', Trust::Trusted);

    File::put($repo.'/updatable-skill/SKILL.md', "---\nname: updatable-skill\ndescription: Version two.\n---\n\n# V2\n");
    Process::run(['git', '-C', $repo, 'add', '-A']);
    Process::run(['git', '-C', $repo, '-c', 'user.email=test@example.com', '-c', 'user.name=Test', 'commit', '-m', 'v2']);

    $result = app(AndreAgroFerreira\AiSdkToolbox\Management\SkillManager::class)->update('updatable-skill');

    expect($result->skill->description)->toBe('Version two.')
        ->and($result->skill->trust)->toBe(Trust::Trusted)
        ->and($result->previousVersion)->not->toBeNull()
        ->and($result->version)->not->toBeNull()
        ->and($result->previousVersion)->not->toBe($result->version)
        ->and(File::get(sys_get_temp_dir().'/ai-update/installed/updatable-skill/SKILL.md'))->toContain('# V2');

    Event::assertDispatched(SkillUpdated::class);
});

it('fails to update skills that are not installed', function (): void {
    app(SkillInstaller::class)->update('missing');
})->throws(SkillInstallException::class, 'not installed');

it('fails to update when the source no longer resolves', function (): void {
    $repo = gitSkillSource();
    $installer = app(SkillInstaller::class);
    $installer->install($repo, force: true);

    File::deleteDirectory($repo);

    $installer->update('updatable-skill');
})->throws(SkillInstallException::class);

it('runs the ai:skill-update command for one and for all', function (): void {
    $repo = gitSkillSource();
    app(SkillInstaller::class)->install($repo, force: true);

    expect(Artisan::call('ai:skill-update', ['name' => 'updatable-skill']))->toBe(0)
        ->and(Artisan::call('ai:skill-update', ['name' => 'missing']))->toBe(1)
        ->and(Artisan::call('ai:skill-update'))->toBe(0);
});

it('updates a skill over HTTP', function (): void {
    config()->set('ai-sdk-toolbox.http.enabled', true);
    AiSdkToolbox::authorize(fn (): bool => true);

    $repo = gitSkillSource();
    app(SkillInstaller::class)->install($repo, force: true);

    $this->postJson('/ai-toolbox/skills/updatable-skill/update')
        ->assertOk()
        ->assertJsonPath('data.skill.name', 'updatable-skill');

    $this->postJson('/ai-toolbox/skills/missing/update')
        ->assertStatus(422);
});

it('blocks forced updates over HTTP by default', function (): void {
    config()->set('ai-sdk-toolbox.http.enabled', true);
    AiSdkToolbox::authorize(fn (): bool => true);

    $repo = gitSkillSource();
    app(SkillInstaller::class)->install($repo, force: true);

    $this->postJson('/ai-toolbox/skills/updatable-skill/update', ['force' => true])
        ->assertStatus(422);
});

afterEach(function (): void {
    AiSdkToolbox::flushAuthorization();
});
