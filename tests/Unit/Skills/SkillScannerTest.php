<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Security\Severity;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillScanner;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\Verdict;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillParser;

function scanFixture(string $name): AndreAgroFerreira\AiSdkToolbox\Skills\Security\ScanReport
{
    $skill = (new SkillParser)->parse(__DIR__.'/../../Fixtures/skills/'.$name.'/SKILL.md');

    return (new SkillScanner)->scan($skill);
}

it('marks a clean skill as safe', function (): void {
    $report = scanFixture('tone-of-voice');

    expect($report->verdict())->toBe(Verdict::Safe)
        ->and($report->isSafe())->toBeTrue()
        ->and($report->findings)->toBeEmpty();
});

it('blocks skills with dangerous PHP functions', function (): void {
    $report = scanFixture('evil-php');

    expect($report->verdict())->toBe(Verdict::Blocked);

    $blocked = $report->findings->filter(fn ($finding): bool => $finding->severity === Severity::Blocked);

    expect($blocked->pluck('rule')->all())->toContain('php.shell_exec')
        ->and($report->findings->pluck('rule')->all())->toContain('php.base64_decode')
        ->and($report->findings->pluck('rule')->all())->toContain('php.file_put_contents');
});

it('warns about prompt injection patterns in the markdown', function (): void {
    $report = scanFixture('evil-injection');

    expect($report->verdict())->toBe(Verdict::Warnings);

    $rules = $report->findings->pluck('rule')->all();

    expect($rules)->toContain('markdown.injection')
        ->and($report->findings->count())->toBeGreaterThanOrEqual(3);
});

it('warns about dangerous patterns in scripts without blocking', function (): void {
    $report = scanFixture('evil-script');

    expect($report->verdict())->toBe(Verdict::Warnings);

    $messages = $report->findings->map->message->implode(' ');

    expect($messages)->toContain('subprocess')
        ->and($messages)->toContain('environment variables')
        ->and($messages)->toContain('network requests');
});
