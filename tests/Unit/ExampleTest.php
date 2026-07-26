<?php

declare(strict_types=1);

use AndreAgroFerreira\LaravelAiSdkToolbox\LaravelAiSdkToolbox;

it('exposes the current version', function (): void {
    $package = new LaravelAiSdkToolbox;

    expect($package->version())->toBe(LaravelAiSdkToolbox::VERSION);
});
