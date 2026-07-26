<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginDisabled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginEnabled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginRemoved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.http.enabled', true);
    config()->set('ai-sdk-toolbox.skills.paths', ['local' => __DIR__.'/../Fixtures/skills']);
    config()->set('ai-sdk-toolbox.plugins.path', sys_get_temp_dir().'/ai-http-plugins/plugins');

    File::deleteDirectory(sys_get_temp_dir().'/ai-http-plugins');
    File::delete(base_path('ai-plugins.lock'));

    AiSdkToolbox::authorize(fn (): bool => true);
});

afterEach(function (): void {
    AiSdkToolbox::flushAuthorization();
    File::deleteDirectory(sys_get_temp_dir().'/ai-http-plugins');
    File::delete(base_path('ai-plugins.lock'));
});

function httpPluginFixturePath(): string
{
    return __DIR__.'/../Fixtures/plugins/memory-pack';
}

it('lists plugins (empty and after install)', function (): void {
    $this->getJson('/ai-toolbox/plugins')
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()]);

    $this->getJson('/ai-toolbox/plugins')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'memory-pack')
        ->assertJsonPath('data.0.enabled', true);
});

it('installs a plugin over HTTP and dispatches the event', function (): void {
    Event::fake([PluginInstalled::class]);

    $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()])
        ->assertCreated()
        ->assertJsonPath('data.name', 'memory-pack')
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.skills', 'skills')
        ->assertJsonPath('data.agents.0', 'memory-agent')
        ->assertJsonPath('data.listeners.0', 'ToolInvoked');

    Event::assertDispatched(PluginInstalled::class);
});

it('installs a plugin as disabled', function (): void {
    $this->postJson('/ai-toolbox/plugins/install', [
        'source' => httpPluginFixturePath(),
        'disabled' => true,
    ])->assertCreated()->assertJsonPath('data.enabled', false);
});

it('rejects invalid install payloads', function (): void {
    $this->postJson('/ai-toolbox/plugins/install', ['source' => 'foo bar'])->assertUnprocessable();

    $this->postJson('/ai-toolbox/plugins/install', [
        'source' => httpPluginFixturePath(),
        'path' => '../../etc',
    ])->assertUnprocessable();
});

it('answers 422 when the plugin is already installed', function (): void {
    $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()]);
    $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()])
        ->assertStatus(422);
});

it('shows a plugin and answers 404 for unknown ones', function (): void {
    $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()]);

    $this->getJson('/ai-toolbox/plugins/memory-pack')
        ->assertOk()
        ->assertJsonPath('data.version', '1.2.0');

    $this->getJson('/ai-toolbox/plugins/missing')->assertNotFound();
});

it('enables, disables and removes plugins over HTTP', function (): void {
    Event::fake([PluginEnabled::class, PluginDisabled::class, PluginRemoved::class]);

    $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()]);

    $this->postJson('/ai-toolbox/plugins/memory-pack/disable')->assertOk()->assertJsonPath('enabled', false);
    $this->postJson('/ai-toolbox/plugins/memory-pack/enable')->assertOk()->assertJsonPath('enabled', true);

    $this->postJson('/ai-toolbox/plugins/missing/disable')->assertNotFound();

    $this->deleteJson('/ai-toolbox/plugins/memory-pack')->assertOk()->assertJsonPath('removed', true);
    $this->deleteJson('/ai-toolbox/plugins/memory-pack')->assertNotFound();

    Event::assertDispatched(PluginDisabled::class);
    Event::assertDispatched(PluginEnabled::class);
    Event::assertDispatched(PluginRemoved::class);
});

it('throttles plugin install requests', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()]);
    }

    $this->postJson('/ai-toolbox/plugins/install', ['source' => httpPluginFixturePath()])
        ->assertStatus(429);
});
