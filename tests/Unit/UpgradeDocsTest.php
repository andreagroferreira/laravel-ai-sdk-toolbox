<?php

declare(strict_types=1);

function packageDoc(string $path): string
{
    return (string) file_get_contents(__DIR__.'/../../'.$path);
}

it('documents the laravel/ai constraint', function (string $path): void {
    expect(packageDoc($path))->toContain('`laravel/ai` ^1.0');
})->with(['README.md', 'CHANGELOG.md', 'wiki/composite-skills.md', 'wiki/script-execution.md']);

it('no longer offers the old constraint as supported', function (): void {
    expect(str_contains(packageDoc('README.md'), '^0.10'))->toBeFalse();
});

it('documents the middleware signature and its per-step semantics', function (string $path): void {
    expect(packageDoc($path))
        ->toContain('PendingStep')
        ->toContain('once per generation step');
})->with(['README.md', 'CHANGELOG.md', 'wiki/composite-skills.md']);

it('documents that authorizing an approval resume belongs to the application', function (string $path): void {
    expect(packageDoc($path))->toContain('application must authorize who resumes');
})->with(['README.md', 'CHANGELOG.md', 'wiki/script-execution.md', 'wiki/security-model.md', 'wiki/cli-tools.md']);

it('documents what an edited approval runs', function (): void {
    expect(packageDoc('wiki/script-execution.md'))->toContain('Decision::edit()');
});

it('never mentions AgentPrompt in the wiki', function (string $path): void {
    expect(preg_match('/\bAgentPrompt\b/', packageDoc($path)))->toBe(0);
})->with(fn (): array => array_map(
    fn (string $file): string => 'wiki/'.basename($file),
    glob(__DIR__.'/../../wiki/*.md') ?: [],
));

it('documents the 0.5.0 release', function (): void {
    expect(packageDoc('CHANGELOG.md'))->toContain('## [0.5.0]');
});
