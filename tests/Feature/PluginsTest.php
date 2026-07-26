<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Events\PluginDisabled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginEnabled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginInstalled;
use AndreAgroFerreira\AiSdkToolbox\Events\PluginRemoved;
use AndreAgroFerreira\AiSdkToolbox\Plugins\AgentRegistry;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginInstallException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginNotFoundException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManager;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginRegistry;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\MemoryPack\MemoryAgent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Events\ToolInvoked;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths', ['local' => __DIR__.'/../Fixtures/skills']);
    config()->set('ai-sdk-toolbox.plugins.path', sys_get_temp_dir().'/ai-plugins/plugins');

    File::deleteDirectory(sys_get_temp_dir().'/ai-plugins');
    File::delete(base_path('ai-plugins.lock'));

    app()->forgetInstance(PluginRegistry::class);
    app()->forgetInstance(AgentRegistry::class);
});

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/ai-plugins');
    File::delete(base_path('ai-plugins.lock'));
});

function fixturePluginPath(string $name = 'memory-pack'): string
{
    return __DIR__.'/../Fixtures/plugins/'.$name;
}

it('installs a plugin and wires skills, agents and listeners', function (): void {
    Event::fake([PluginInstalled::class]);

    $plugin = app(PluginManager::class)->install(fixturePluginPath());

    expect($plugin->name)->toBe('memory-pack')
        ->and(File::isFile(sys_get_temp_dir().'/ai-plugins/plugins/memory-pack/ai-plugin.json'))->toBeTrue()
        ->and(app(PluginRegistry::class)->has('memory-pack'))->toBeTrue()
        ->and(app(SkillRegistry::class)->has('memory-tip'))->toBeTrue()
        ->and(app(AgentRegistry::class)->has('memory-agent'))->toBeTrue()
        ->and(app('events')->hasListeners(ToolInvoked::class))->toBeTrue();

    Event::assertDispatched(PluginInstalled::class);
});

it('resolves a named agent from the registry with constructor args', function (): void {
    app(PluginManager::class)->install(fixturePluginPath());

    $agent = app(AgentRegistry::class)->make('memory-agent', scope: 'tenant-1');

    if (! $agent instanceof MemoryAgent) {
        PHPUnit\Framework\Assert::fail('Expected a MemoryAgent.');
    }

    expect($agent->scope)->toBe('tenant-1');
});

it('refuses to install the same plugin twice', function (): void {
    $manager = app(PluginManager::class);
    $manager->install(fixturePluginPath());
    $manager->install(fixturePluginPath());
})->throws(PluginInstallException::class, 'already installed');

it('removes a plugin and its registration', function (): void {
    Event::fake([PluginRemoved::class]);

    $manager = app(PluginManager::class);
    $manager->install(fixturePluginPath());
    $manager->remove('memory-pack');

    expect(app(PluginRegistry::class)->has('memory-pack'))->toBeFalse()
        ->and(File::isDirectory(sys_get_temp_dir().'/ai-plugins/plugins/memory-pack'))->toBeFalse();

    Event::assertDispatched(PluginRemoved::class);
});

it('fails to remove unknown plugins', function (): void {
    app(PluginManager::class)->remove('missing');
})->throws(PluginNotFoundException::class);

it('enables and disables plugins with events', function (): void {
    Event::fake([PluginEnabled::class, PluginDisabled::class]);

    $manager = app(PluginManager::class);
    $manager->install(fixturePluginPath(), enabled: false);

    expect(app(PluginRegistry::class)->get('memory-pack') ?? [])->toHaveKey('enabled', false);

    $manager->enable('memory-pack');

    expect(app(PluginRegistry::class)->get('memory-pack') ?? [])->toHaveKey('enabled', true);

    $manager->disable('memory-pack');

    expect(app(PluginRegistry::class)->get('memory-pack') ?? [])->toHaveKey('enabled', false);

    Event::assertDispatched(PluginEnabled::class);
    Event::assertDispatched(PluginDisabled::class);
});

it('runs the plugin commands', function (): void {
    expect(Artisan::call('ai:plugin-install', ['source' => fixturePluginPath()]))->toBe(0)
        ->and(Artisan::call('ai:plugin-list'))->toBe(0)
        ->and(Artisan::call('ai:plugin-disable', ['name' => 'memory-pack']))->toBe(0)
        ->and(Artisan::call('ai:plugin-enable', ['name' => 'memory-pack']))->toBe(0)
        ->and(Artisan::call('ai:plugin-remove', ['name' => 'memory-pack']))->toBe(0)
        ->and(Artisan::call('ai:plugin-remove', ['name' => 'memory-pack']))->toBe(1);
});

it('tolerates broken plugins at boot', function (): void {
    $registry = app(PluginRegistry::class);

    $registry->put(new AndreAgroFerreira\AiSdkToolbox\Plugins\Plugin(
        name: 'ghost',
        version: '0.0.1',
        description: 'A plugin whose files were deleted.',
        basePath: sys_get_temp_dir().'/ai-plugins/does-not-exist',
    ), 'test', true);

    $registry->bootEnabled();

    expect(true)->toBeTrue();
});

it('returns no composer plugins when nothing declares them', function (): void {
    expect(app(PluginManager::class)->installComposerPlugins())->toBe([]);
});
