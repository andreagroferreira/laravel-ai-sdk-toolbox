<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Skills\Tools\RunSkillScript;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths', ['local' => __DIR__.'/../Fixtures/skills']);
    config()->set('ai-sdk-toolbox.skills.trust.sources', ['local' => 'trusted']);
});

it('runs a script through the tool', function (): void {
    $result = (new RunSkillScript)->handle(new Request(['skill' => 'backup', 'script' => 'backup.py', 'args' => ['weekly']]));

    expect((string) $result)->toContain('backup done: weekly');
});

it('returns an error for unknown skills', function (): void {
    $result = (new RunSkillScript)->handle(new Request(['skill' => 'missing', 'script' => 'x.py']));

    expect((string) $result)->toContain('Error:');
});

it('rejects skills outside the agent allowlist', function (): void {
    $result = (new RunSkillScript(['tone-of-voice']))->handle(new Request(['skill' => 'backup', 'script' => 'backup.py']));

    expect((string) $result)->toContain('not available in this agent');
});

it('returns an error for failing scripts', function (): void {
    $result = (new RunSkillScript)->handle(new Request(['skill' => 'backup', 'script' => 'helper.sh']));

    expect((string) $result)->toContain('Error:');
});

it('does not require approval for trusted skills by default', function (): void {
    $approval = (new RunSkillScript)->shouldRequestApproval(new Request(['skill' => 'backup', 'script' => 'backup.py']));

    expect($approval)->toBeNull();
});

it('requires approval for untrusted skills by default', function (): void {
    config()->set('ai-sdk-toolbox.skills.trust.sources', ['local' => 'untrusted']);

    $approval = (new RunSkillScript)->shouldRequestApproval(new Request(['skill' => 'backup', 'script' => 'backup.py']));

    if (! $approval instanceof Laravel\Ai\Approvals\Approval) {
        PHPUnit\Framework\Assert::fail('Expected an approval to be required.');
    }

    expect($approval->reason)->toContain('untrusted skill [backup]');
});

it('always requires approval when configured as always', function (): void {
    config()->set('ai-sdk-toolbox.scripts.approval', 'always');

    $approval = (new RunSkillScript)->shouldRequestApproval(new Request(['skill' => 'backup', 'script' => 'backup.py']));

    expect($approval)->not->toBeNull();
});

it('never requires approval when configured as never', function (): void {
    config()->set('ai-sdk-toolbox.scripts.approval', 'never');
    config()->set('ai-sdk-toolbox.skills.trust.sources', ['local' => 'untrusted']);

    $approval = (new RunSkillScript)->shouldRequestApproval(new Request(['skill' => 'backup', 'script' => 'backup.py']));

    expect($approval)->toBeNull();
});

it('honours the manual approval overrides', function (): void {
    $approval = (new RunSkillScript)->requireApproval('Review first.')->shouldRequestApproval(new Request(['skill' => 'backup', 'script' => 'backup.py']));

    if (! $approval instanceof Laravel\Ai\Approvals\Approval) {
        PHPUnit\Framework\Assert::fail('Expected an approval to be required.');
    }

    expect($approval->reason)->toBe('Review first.')
        ->and((new RunSkillScript)->withoutApproval()->shouldRequestApproval(new Request(['skill' => 'backup', 'script' => 'backup.py'])))
        ->toBeNull();
});
