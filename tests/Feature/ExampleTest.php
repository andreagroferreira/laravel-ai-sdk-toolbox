<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Facades\AiSdkToolbox;

it('resolves the package from the container', function (): void {
    expect(AiSdkToolbox::version())->toBe(AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox::VERSION);
});

it('merges the package configuration', function (): void {
    expect(config('ai-sdk-toolbox.skills.trust.default'))->toBe('untrusted')
        ->and(config('ai-sdk-toolbox.scripts.timeout'))->toBe(30);
});
