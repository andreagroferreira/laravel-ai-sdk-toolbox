<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;

it('keeps the version constant equal to the latest released changelog entry', function (): void {
    $changelog = (string) file_get_contents(__DIR__.'/../../CHANGELOG.md');

    preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $latestRelease);

    expect(AiSdkToolbox::VERSION)->toBe($latestRelease[1] ?? null);
});
