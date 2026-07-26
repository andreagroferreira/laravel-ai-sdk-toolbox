<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\InvalidPluginManifestException;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManifest;

it('parses a toolbox manifest', function (): void {
    $plugin = (new PluginManifest)->parse(__DIR__.'/../../Fixtures/plugins/memory-pack');

    expect($plugin->name)->toBe('memory-pack')
        ->and($plugin->version)->toBe('1.2.0')
        ->and($plugin->skillsPath)->toBe('skills')
        ->and($plugin->fullSkillsPath())->toEndWith('memory-pack'.DIRECTORY_SEPARATOR.'skills')
        ->and($plugin->agents)->toHaveKey('memory-agent')
        ->and($plugin->listeners)->toHaveKey('ToolInvoked')
        ->and($plugin->hasSkills())->toBeTrue();
});

it('parses the claude plugin format', function (): void {
    $plugin = (new PluginManifest)->parse(__DIR__.'/../../Fixtures/plugins/claude-plugin');

    expect($plugin->name)->toBe('claude-plugin')
        ->and($plugin->skillsPath)->toBe('skills')
        ->and($plugin->agents)->toBe([]);
});

it('rejects a manifest without a valid name', function (): void {
    (new PluginManifest)->parse(__DIR__.'/../../Fixtures/plugins/broken-plugin');
})->throws(InvalidPluginManifestException::class, 'name');

it('rejects directories without a manifest', function (): void {
    (new PluginManifest)->parse(__DIR__.'/../../Fixtures/skills');
})->throws(InvalidPluginManifestException::class, 'No ai-plugin.json');
