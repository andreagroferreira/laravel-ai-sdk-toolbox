<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolScanner;
use AndreAgroFerreira\AiSdkToolbox\CliTools\Tools\RunCliTool;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\Exceptions\ScriptExecutionException;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\LocalScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillInstaller;
use AndreAgroFerreira\AiSdkToolbox\Skills\Trust;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.skills.paths', [
        'local' => __DIR__.'/../Fixtures/skills',
        'installed' => sys_get_temp_dir().'/ai-cli-tools/installed',
    ]);
    config()->set('ai-sdk-toolbox.cli_tools.path', sys_get_temp_dir().'/ai-cli-tools/tools');

    File::deleteDirectory(sys_get_temp_dir().'/ai-cli-tools');
});

afterEach(function (): void {
    File::deleteDirectory(sys_get_temp_dir().'/ai-cli-tools');
});

function installToolSource(): AndreAgroFerreira\AiSdkToolbox\Skills\InstallManyResult
{
    return app(SkillInstaller::class)->installMany(__DIR__.'/../Fixtures/tool-source', force: true);
}

it('extracts required environment variables from CLI files', function (): void {
    $env = (new CliToolScanner)->requiredEnvironment(__DIR__.'/../Fixtures/tool-source/tools/clis/ga4.js');

    expect($env)->toBe(['GA4_ACCESS_TOKEN']);
});

it('registers CLI tools in the lock when installing a source with tools', function (): void {
    $result = installToolSource();

    $names = array_map(fn ($tool): string => $tool->name, $result->cliTools);

    expect($names)->toContain('ga4')
        ->and($names)->toContain('env_dump')
        ->and(app(SkillLock::class)->cliToolEntries())->toHaveKey('ga4')
        ->and(glob(sys_get_temp_dir().'/ai-cli-tools/tools/*/clis/ga4.js') ?: [])->not->toBeEmpty();

    $ga4 = app(CliToolRegistry::class)->resolve('ga4');

    expect($ga4->env)->toBe(['GA4_ACCESS_TOKEN'])
        ->and($ga4->trust)->toBe(Trust::Untrusted)
        ->and($ga4->runtime)->toBe('node');
});

it('injects only the declared environment variables into the CLI process', function (): void {
    installToolSource();

    putenv('GA4_ACCESS_TOKEN=secret-ga4-token');
    putenv('KLAVIYO_API_KEY=secret-klaviyo-key');

    $result = app(LocalScriptExecutor::class)->runCli(app(CliToolRegistry::class)->resolve('env_dump'), []);

    expect($result->successful())->toBeTrue()
        ->and($result->output)->toContain('GA4_ACCESS_TOKEN')
        ->and($result->output)->not->toContain('KLAVIYO_API_KEY')
        ->and($result->output)->toContain('PATH');

    putenv('GA4_ACCESS_TOKEN');
    putenv('KLAVIYO_API_KEY');
});

it('runs a CLI tool through the RunCliTool tool', function (): void {
    installToolSource();
    putenv('GA4_ACCESS_TOKEN=secret-ga4-token');

    $result = (new RunCliTool)->handle(new Request(['cli' => 'ga4', 'args' => ['report']]));

    expect((string) $result)->toContain('ga4 ok: report')
        ->and((string) $result)->toContain('token:secr');

    putenv('GA4_ACCESS_TOKEN');
});

it('surfaces the CLI error when the environment is missing', function (): void {
    installToolSource();
    putenv('GA4_ACCESS_TOKEN');

    $result = (new RunCliTool)->handle(new Request(['cli' => 'ga4', 'args' => []]));

    expect((string) $result)->toContain('GA4_ACCESS_TOKEN environment variable required');
});

it('requires approval for untrusted CLI tools by default', function (): void {
    installToolSource();

    $approval = (new RunCliTool)->shouldRequestApproval(new Request(['cli' => 'ga4']));

    if (! $approval instanceof Laravel\Ai\Approvals\Approval) {
        PHPUnit\Framework\Assert::fail('Expected an approval to be required.');
    }

    expect($approval->reason)->toContain('untrusted');
});

it('does not require approval once the CLI tool is trusted', function (): void {
    installToolSource();
    app(SkillLock::class)->setCliToolTrust('ga4', Trust::Trusted);

    $approval = (new RunCliTool)->shouldRequestApproval(new Request(['cli' => 'ga4']));

    expect($approval)->toBeNull();
});

it('refuses to run CLI tools when disabled', function (): void {
    installToolSource();
    config()->set('ai-sdk-toolbox.cli_tools.enabled', false);

    app(LocalScriptExecutor::class)->runCli(app(CliToolRegistry::class)->resolve('ga4'), []);
})->throws(ScriptExecutionException::class, 'disabled');

it('lists CLI tools with their environment status', function (): void {
    installToolSource();
    putenv('GA4_ACCESS_TOKEN=secret-ga4-token');

    expect(Artisan::call('ai:tool-list'))->toBe(0);

    putenv('GA4_ACCESS_TOKEN');
});

it('verifies CLI tool integrity and reports tampering', function (): void {
    installToolSource();

    expect(Artisan::call('ai:skill-verify'))->toBe(0);

    $path = app(CliToolRegistry::class)->resolve('ga4')->path;
    File::put($path, File::get($path).'// tampered');

    expect(Artisan::call('ai:skill-verify'))->toBe(1);
});
