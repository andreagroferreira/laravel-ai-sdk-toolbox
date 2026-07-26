<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;

it('exposes the current version', function (): void {
    $package = new AiSdkToolbox;

    expect($package->version())->toBe(AiSdkToolbox::VERSION);
});
