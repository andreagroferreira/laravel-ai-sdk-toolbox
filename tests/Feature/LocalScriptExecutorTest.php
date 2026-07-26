<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\Exceptions\ScriptExecutionException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\LocalScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths', ['local' => __DIR__.'/../Fixtures/skills']);
});

function backupSkill(): AndreAgroFerreira\AiSdkToolbox\Skills\Skill
{
    return app(SkillRegistry::class)->resolve('backup');
}

it('runs a python script and captures the output', function (): void {
    $result = app(LocalScriptExecutor::class)->run(backupSkill(), 'backup.py', ['daily']);

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toContain('backup done: daily')
        ->and($result->exitCode)->toBe(0);
});

it('scrubs the environment passed to the script', function (): void {
    putenv('APP_KEY=base64:supersecretkeythatmustnotleak');
    $_ENV['APP_KEY'] = 'base64:supersecretkeythatmustnotleak';

    $result = app(LocalScriptExecutor::class)->run(backupSkill(), 'env_dump.py', []);

    expect($result->successful())->toBeTrue()
        ->and($result->output)->not->toContain('APP_KEY')
        ->and($result->output)->toContain('PATH');

    putenv('APP_KEY');
});

it('rejects scripts not declared by the skill', function (): void {
    app(LocalScriptExecutor::class)->run(backupSkill(), '../SKILL.md', []);
})->throws(ScriptExecutionException::class, 'not declared');

it('rejects scripts without an allowed runtime', function (): void {
    app(LocalScriptExecutor::class)->run(backupSkill(), 'helper.sh', []);
})->throws(ScriptExecutionException::class, 'No runtime is allowed');

it('rejects execution when disabled', function (): void {
    config()->set('ai-sdk-toolbox.scripts.enabled', false);

    app(LocalScriptExecutor::class)->run(backupSkill(), 'backup.py', []);
})->throws(ScriptExecutionException::class, 'disabled');

it('rejects symlinks escaping the scripts directory', function (): void {
    $link = __DIR__.'/../Fixtures/skills/backup/scripts/escape.py';

    if (! file_exists($link)) {
        symlink(__DIR__.'/../Fixtures/skills/backup/SKILL.md', $link);
    }

    try {
        app(LocalScriptExecutor::class)->run(backupSkill(), 'escape.py', []);
    } catch (ScriptExecutionException $scriptExecutionException) {
        expect($scriptExecutionException->getMessage())->toContain('outside');

        return;
    } finally {
        @unlink($link);
    }

    PHPUnit\Framework\Assert::fail('Expected a ScriptExecutionException for the escaping symlink.');
});

it('truncates long output', function (): void {
    config()->set('ai-sdk-toolbox.scripts.max_output', 10);

    $result = app(LocalScriptExecutor::class)->run(backupSkill(), 'backup.py', ['a-very-long-argument-here']);

    expect(mb_strlen($result->output))->toBeLessThanOrEqual(11);
});
