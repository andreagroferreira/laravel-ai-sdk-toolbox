<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\CliTools\Tools\RunCliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\LocalScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\ScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use AndreAgroFerreira\AiSdkToolbox\Skills\Tools\RunSkillScript;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Agents\ApprovalAgent;
use AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\RecordingScriptExecutor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Laravel\Ai\Responses\AgentResponse;

beforeEach(function (): void {
    Artisan::call('migrate', ['--path' => __DIR__.'/../../vendor/laravel/ai/database/migrations', '--realpath' => true]);

    config()->set('ai.conversations.generate_title', false);
    config()->set('ai.providers.anthropic.key', 'test-key');
    config()->set('ai-sdk-toolbox.skills.paths', [
        'local' => __DIR__.'/../Fixtures/skills',
        'installed' => sys_get_temp_dir().'/ai-approval-loop/installed',
    ]);
    config()->set('ai-sdk-toolbox.cli_tools.path', sys_get_temp_dir().'/ai-approval-loop/tools');
    config()->set('ai-sdk-toolbox.skills.trust.sources', ['local' => 'untrusted']);

    File::deleteDirectory(sys_get_temp_dir().'/ai-approval-loop');
    putenv('GA4_ACCESS_TOKEN=secret-ga4-token');

    app()->instance(ScriptExecutor::class, new RecordingScriptExecutor(app(LocalScriptExecutor::class)));
});

afterEach(function (): void {
    putenv('GA4_ACCESS_TOKEN');
    File::deleteDirectory(sys_get_temp_dir().'/ai-approval-loop');
});

/**
 * @param  array<string, mixed>  $arguments
 */
function fakeToolCallThenReply(string $tool, array $arguments): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::sequence([
            Http::response([
                'id' => 'msg_1',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-6',
                'content' => [['type' => 'tool_use', 'id' => 'call-1', 'name' => $tool, 'input' => $arguments]],
                'stop_reason' => 'tool_use',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ]),
            Http::response([
                'id' => 'msg_2',
                'type' => 'message',
                'role' => 'assistant',
                'model' => 'claude-sonnet-4-6',
                'content' => [['type' => 'text', 'text' => 'done']],
                'stop_reason' => 'end_turn',
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
            ]),
        ]),
    ]);
}

/**
 * @return array<int, array{target: string, args: array<int, string>}>
 */
function recordedRuns(): array
{
    $executor = app(ScriptExecutor::class);

    if (! $executor instanceof RecordingScriptExecutor) {
        throw new RuntimeException('The recording executor is not bound.');
    }

    return $executor->runs;
}

function conversationIdOf(AgentResponse $response): string
{
    return $response->conversationId ?? throw new RuntimeException('The paused turn has no conversation.');
}

function approvalUser(): object
{
    return (object) ['id' => 1];
}

function scriptAgent(): ApprovalAgent
{
    return new ApprovalAgent([new RunSkillScript]);
}

function cliAgent(): ApprovalAgent
{
    app(SkillInstaller::class)->installMany(__DIR__.'/../Fixtures/tool-source', force: true);

    return new ApprovalAgent([new RunCliTool]);
}

function agentFor(string $tool): ApprovalAgent
{
    return $tool === 'RunCliTool' ? cliAgent() : scriptAgent();
}

function pauseTool(string $tool): AgentResponse
{
    fakeToolCallThenReply($tool, $tool === 'RunCliTool'
        ? ['cli' => 'ga4', 'args' => []]
        : ['skill' => 'backup', 'script' => 'backup.py', 'args' => []]);

    return agentFor($tool)->forUser(approvalUser())->prompt('run it', provider: 'anthropic');
}

it('pauses an untrusted skill script without running it', function (): void {
    fakeToolCallThenReply('RunSkillScript', ['skill' => 'backup', 'script' => 'backup.py', 'args' => ['weekly']]);

    $response = scriptAgent()->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    expect($response->pendingApprovals)->toHaveCount(1)
        ->and($response->pendingApprovals->first()?->arguments['script'] ?? null)->toBe('backup.py')
        ->and(recordedRuns())->toBe([]);
});

it('pauses an untrusted CLI tool without running it', function (): void {
    fakeToolCallThenReply('RunCliTool', ['cli' => 'ga4', 'args' => ['report']]);

    $response = cliAgent()->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    expect($response->pendingApprovals)->toHaveCount(1)
        ->and($response->pendingApprovals->first()?->arguments['cli'] ?? null)->toBe('ga4')
        ->and(recordedRuns())->toBe([]);
});

it('runs a trusted skill script in the loop with no pending approval', function (): void {
    config()->set('ai-sdk-toolbox.skills.trust.sources', ['local' => 'trusted']);
    fakeToolCallThenReply('RunSkillScript', ['skill' => 'backup', 'script' => 'backup.py', 'args' => ['weekly']]);

    $response = scriptAgent()->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    expect($response->pendingApprovals)->toHaveCount(0)
        ->and($response->text)->toBe('done')
        ->and(recordedRuns())->toBe([['target' => 'backup/backup.py', 'args' => ['weekly']]]);
});

it('runs exactly the approved skill script with the approved arguments', function (): void {
    fakeToolCallThenReply('RunSkillScript', ['skill' => 'backup', 'script' => 'backup.py', 'args' => ['weekly']]);
    $paused = scriptAgent()->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    $resumed = scriptAgent()->continue(conversationIdOf($paused), approvalUser())->prompt(Decisions::from(['call-1' => true]), provider: 'anthropic');

    expect(recordedRuns())->toBe([['target' => 'backup/backup.py', 'args' => ['weekly']]])
        ->and($resumed->pendingApprovals)->toHaveCount(0)
        ->and($resumed->toolResults->first()?->result)->toContain('backup done: weekly');
});

it('runs exactly the approved CLI tool with the approved arguments', function (): void {
    fakeToolCallThenReply('RunCliTool', ['cli' => 'ga4', 'args' => ['report']]);
    $paused = cliAgent()->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    $resumed = cliAgent()->continue(conversationIdOf($paused), approvalUser())->prompt(Decisions::from(['call-1' => true]), provider: 'anthropic');

    expect(recordedRuns())->toBe([['target' => 'ga4', 'args' => ['report']]])
        ->and($resumed->toolResults->first()?->result)->toContain('ga4 ok: report');
});

it('never runs a rejected skill script or CLI tool', function (string $tool): void {
    $paused = pauseTool($tool);

    $resumed = agentFor($tool)
        ->continue(conversationIdOf($paused), approvalUser())
        ->prompt(Decisions::from(['call-1' => false]), provider: 'anthropic');

    expect(recordedRuns())->toBe([])
        ->and($resumed->toolResults->first()?->denied)->toBeTrue();
})->with(['RunSkillScript', 'RunCliTool']);

it('refuses a resume whose tool call id is not pending and runs nothing', function (string $tool): void {
    $paused = pauseTool($tool);

    expect(fn () => agentFor($tool)
        ->continue(conversationIdOf($paused), approvalUser())
        ->prompt(Decisions::from(['call-other' => true]), provider: 'anthropic'))
        ->toThrow(ApprovalMismatchException::class);

    expect(recordedRuns())->toBe([]);
})->with(['RunSkillScript', 'RunCliTool']);

it('runs a trusted CLI tool in the loop with no pending approval', function (): void {
    fakeToolCallThenReply('RunCliTool', ['cli' => 'ga4', 'args' => ['report']]);
    $agent = cliAgent();
    app(SkillLock::class)->setCliToolTrust('ga4', Trust::Trusted);

    $response = $agent->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    expect($response->pendingApprovals)->toHaveCount(0)
        ->and(recordedRuns())->toBe([['target' => 'ga4', 'args' => ['report']]]);
});

it('pauses a skill from a source with no trust configuration', function (): void {
    config()->set('ai-sdk-toolbox.skills.trust.sources', []);
    fakeToolCallThenReply('RunSkillScript', ['skill' => 'backup', 'script' => 'backup.py', 'args' => []]);

    $response = scriptAgent()->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    expect($response->pendingApprovals)->toHaveCount(1)
        ->and(recordedRuns())->toBe([]);
});

it('pauses a CLI tool whose trust was revoked', function (): void {
    fakeToolCallThenReply('RunCliTool', ['cli' => 'ga4', 'args' => []]);
    $agent = cliAgent();
    app(SkillLock::class)->setCliToolTrust('ga4', Trust::Trusted);
    app(SkillLock::class)->setCliToolTrust('ga4', Trust::Untrusted);

    $response = $agent->forUser(approvalUser())->prompt('run it', provider: 'anthropic');

    expect($response->pendingApprovals)->toHaveCount(1)
        ->and(recordedRuns())->toBe([]);
});
