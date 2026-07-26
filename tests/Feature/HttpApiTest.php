<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillTrustChanged;
use AndreAgroFerreira\AiSdkToolbox\Events\SkillUninstalled;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.http.enabled', true);
    config()->set('ai-sdk-toolbox.skills.paths', [
        'local' => __DIR__.'/../Fixtures/skills',
        'installed' => sys_get_temp_dir().'/ai-http/installed',
    ]);
    config()->set('ai-sdk-toolbox.cli_tools.path', sys_get_temp_dir().'/ai-http/tools');

    File::deleteDirectory(sys_get_temp_dir().'/ai-http');

    AiSdkToolbox::authorize(fn (): bool => true);
});

afterEach(function (): void {
    AiSdkToolbox::flushAuthorization();
    File::deleteDirectory(sys_get_temp_dir().'/ai-http');
});

it('answers 404 when the HTTP layer is disabled', function (): void {
    config()->set('ai-sdk-toolbox.http.enabled', false);

    $this->getJson('/ai-toolbox/skills')->assertNotFound();
});

it('answers 403 without an authorization callback outside the local environment', function (): void {
    AiSdkToolbox::flushAuthorization();

    $this->getJson('/ai-toolbox/skills')->assertForbidden();
});

it('lists skills when authorized', function (): void {
    $this->getJson('/ai-toolbox/skills')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'backup');
});

it('installs a skill over HTTP and dispatches the event', function (): void {
    Event::fake([SkillInstalled::class]);

    $this->postJson('/ai-toolbox/skills/install', [
        'source' => __DIR__.'/../Fixtures/skills/tone-of-voice',
    ])->assertCreated()
        ->assertJsonPath('data.installed.0.name', 'tone-of-voice')
        ->assertJsonPath('data.installed.0.trust', 'untrusted');

    expect(app(SkillLock::class)->has('tone-of-voice'))->toBeTrue();

    Event::assertDispatched(SkillInstalled::class, fn (SkillInstalled $event): bool => $event->skill->name === 'tone-of-voice');
});

it('refuses blocked skills over HTTP even with force', function (): void {
    $this->postJson('/ai-toolbox/skills/install', [
        'source' => __DIR__.'/../Fixtures/skills/evil-php',
        'force' => true,
    ])->assertStatus(422);

    expect(File::isDirectory(sys_get_temp_dir().'/ai-http/installed/evil-php'))->toBeFalse();
});

it('allows force installs when explicitly enabled', function (): void {
    config()->set('ai-sdk-toolbox.http.allow_force', true);

    $this->postJson('/ai-toolbox/skills/install', [
        'source' => __DIR__.'/../Fixtures/skills/evil-php',
        'force' => true,
    ])->assertCreated();
});

it('requires accept_warnings for skills with scan warnings', function (): void {
    $source = __DIR__.'/../Fixtures/skills/evil-injection';

    $this->postJson('/ai-toolbox/skills/install', ['source' => $source])
        ->assertStatus(422);

    $this->postJson('/ai-toolbox/skills/install', ['source' => $source, 'accept_warnings' => true])
        ->assertCreated();
});

it('validates the install payload', function (): void {
    $this->postJson('/ai-toolbox/skills/install', ['source' => 'foo bar baz'])
        ->assertUnprocessable();

    $this->postJson('/ai-toolbox/skills/install', [
        'source' => __DIR__.'/../Fixtures/skills',
        'path' => '../../etc',
    ])->assertUnprocessable();
});

it('shows, removes and audits skills', function (): void {
    $this->postJson('/ai-toolbox/skills/install', ['source' => __DIR__.'/../Fixtures/skills/tone-of-voice']);

    $this->getJson('/ai-toolbox/skills/tone-of-voice')->assertOk()->assertJsonPath('data.name', 'tone-of-voice');
    $this->getJson('/ai-toolbox/skills/missing')->assertNotFound();
    $this->getJson('/ai-toolbox/skills/tone-of-voice/audit')->assertOk()->assertJsonPath('data.verdict', 'safe');

    Event::fake([SkillUninstalled::class]);

    $this->deleteJson('/ai-toolbox/skills/tone-of-voice')->assertOk()->assertJsonPath('removed', true);

    Event::assertDispatched(SkillUninstalled::class);
});

it('promotes and demotes trust over HTTP', function (): void {
    Event::fake([SkillTrustChanged::class]);

    $this->postJson('/ai-toolbox/skills/install', ['source' => __DIR__.'/../Fixtures/skills/tone-of-voice']);

    $this->postJson('/ai-toolbox/skills/tone-of-voice/trust')->assertOk()->assertJsonPath('trust', 'trusted');
    $this->deleteJson('/ai-toolbox/skills/tone-of-voice/trust')->assertOk()->assertJsonPath('trust', 'untrusted');

    Event::assertDispatched(SkillTrustChanged::class);
});

it('lists CLI tools with environment status only', function (): void {
    app(AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller::class)->installMany(__DIR__.'/../Fixtures/tool-source', force: true);

    putenv('GA4_ACCESS_TOKEN=secret-token');

    $this->getJson('/ai-toolbox/cli-tools')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'env_dump')
        ->assertJsonMissing(['GA4_ACCESS_TOKEN' => 'secret-token']);

    putenv('GA4_ACCESS_TOKEN');
});

it('verifies integrity over HTTP', function (): void {
    $this->postJson('/ai-toolbox/skills/install', ['source' => __DIR__.'/../Fixtures/skills/tone-of-voice']);

    $this->getJson('/ai-toolbox/verify')->assertOk()->assertJsonPath('ok', true);

    File::put(sys_get_temp_dir().'/ai-http/installed/tone-of-voice/SKILL.md', 'tampered');

    $this->getJson('/ai-toolbox/verify')->assertOk()->assertJsonPath('ok', false);
});

it('throttles install requests', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/ai-toolbox/skills/install', ['source' => __DIR__.'/../Fixtures/skills/tone-of-voice']);
    }

    $this->postJson('/ai-toolbox/skills/install', ['source' => __DIR__.'/../Fixtures/skills/tone-of-voice'])
        ->assertStatus(429);
});
