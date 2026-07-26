<?php

declare(strict_types=1);

use AndreAgroFerreira\LaravelAiSdkToolbox\Facades\LaravelAiSdkToolbox;
use Illuminate\Support\Facades\Schema;

it('resolves the package from the container', function (): void {
    expect(LaravelAiSdkToolbox::version())->toBe('0.1.0');
});

it('merges the package configuration', function (): void {
    expect(config('laravel-ai-sdk-toolbox.enabled'))->toBeTrue();
});

it('loads the package migrations', function (): void {
    expect(Schema::hasTable('laravel_ai_sdk_toolbox_items'))->toBeTrue();
});
