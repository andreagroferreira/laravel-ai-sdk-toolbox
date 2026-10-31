<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function workflow(string $name): array
{
    /** @var array<string, mixed> $parsed */
    $parsed = Yaml::parseFile(__DIR__.'/../../.github/workflows/'.$name);

    return $parsed;
}

/**
 * @return array<string, mixed>
 */
function publishJob(): array
{
    /** @var array{jobs: array{update-packagist: array<string, mixed>}} $packagist */
    $packagist = workflow('packagist.yml');

    return $packagist['jobs']['update-packagist'];
}

/**
 * @return array<string, mixed>
 */
function publishStep(string $name): array
{
    /** @var array<int, array<string, mixed>> $steps */
    $steps = publishJob()['steps'];

    foreach ($steps as $step) {
        if (($step['name'] ?? null) === $name) {
            return $step;
        }
    }

    throw new RuntimeException(sprintf('Missing step [%s].', $name));
}

it('publishes only after the tests workflow passes, without handing it secrets', function (): void {
    /** @var array{jobs: array{tests: array<string, mixed>}} $packagist */
    $packagist = workflow('packagist.yml');

    expect($packagist['jobs']['tests']['uses'])->toBe('./.github/workflows/tests.yml')
        ->and(array_key_exists('secrets', $packagist['jobs']['tests']))->toBeFalse()
        ->and(publishJob()['needs'])->toBe('tests');
});

it('lets the tests workflow be called while keeping its triggers', function (): void {
    /** @var array{on: array<string, mixed>} $tests */
    $tests = workflow('tests.yml');

    expect(array_key_exists('workflow_call', $tests['on']))->toBeTrue()
        ->and($tests['on']['push'])->toBe(['branches' => ['main']])
        ->and($tests['on']['pull_request'])->toBe(['branches' => ['main']]);
});

it('compares the tag with the version only on a tag push', function (): void {
    $step = publishStep('Check the tag matches VERSION');

    expect($step['if'])->toBe("github.event_name == 'push'")
        ->and($step['run'])->toContain("grep -qF \"VERSION = '\${version}'\" src/AiSdkToolbox.php")
        ->and($step['run'])->toContain('exit 1');
});

it('requires the tagged commit to be on main, with the full history', function (): void {
    /** @var array<int, array<string, mixed>> $steps */
    $steps = publishJob()['steps'];
    $step = publishStep('Check the tagged commit is on main');

    expect($steps[0]['with'] ?? null)->toBe(['fetch-depth' => 0])
        ->and($step['if'])->toBe("github.event_name == 'push'")
        ->and($step['run'])->toContain('git merge-base --is-ancestor')
        ->and($step['run'])->toContain('origin/main')
        ->and($step['run'])->toContain('exit 1');
});

it('runs both checks before the Packagist update', function (): void {
    /** @var array<int, array{name?: string}> $steps */
    $steps = publishJob()['steps'];
    $names = array_map(fn (array $step): string => $step['name'] ?? '', $steps);

    $positions = array_flip($names);

    expect($positions['Check the tag matches VERSION'])->toBeLessThan($positions['Trigger Packagist update'])
        ->and($positions['Check the tagged commit is on main'])->toBeLessThan($positions['Trigger Packagist update']);
});

it('runs a manual dispatch only on main', function (): void {
    expect(publishJob()['if'] ?? null)->toBe("github.event_name == 'push' || github.ref == 'refs/heads/main'");
});
