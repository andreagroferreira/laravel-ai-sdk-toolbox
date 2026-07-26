<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use AndreAgroFerreira\AiSdkToolbox\Console\CliToolListCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillAuditCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillInstallCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillListCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillMakeCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillRemoveCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillShowCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillTrustCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillVerifyCommand;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\LocalScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\Scripts\ScriptExecutor;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use AndreAgroFerreira\AiSdkToolbox\Skills\SkillRegistry;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AiSdkToolboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-sdk-toolbox.php', 'ai-sdk-toolbox');

        $this->app->singleton(AiSdkToolbox::class);
        $this->app->singleton(SkillRegistry::class);
        $this->app->singleton(CliToolRegistry::class);
        $this->app->singleton(ScriptExecutor::class, LocalScriptExecutor::class);
        $this->app->singleton(SkillLock::class, SkillLock::atDefaultLocation(...));
    }

    public function boot(): void
    {
        AboutCommand::add('AiSdkToolbox', fn (): array => ['Version' => AiSdkToolbox::VERSION]);

        $this->registerHttpRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-sdk-toolbox.php' => config_path('ai-sdk-toolbox.php'),
            ], 'ai-sdk-toolbox-config');

            $this->commands([
                CliToolListCommand::class,
                SkillAuditCommand::class,
                SkillInstallCommand::class,
                SkillListCommand::class,
                SkillMakeCommand::class,
                SkillRemoveCommand::class,
                SkillShowCommand::class,
                SkillTrustCommand::class,
                SkillVerifyCommand::class,
            ]);
        }
    }

    private function registerHttpRoutes(): void
    {
        /** @var Repository $config */
        $config = $this->app->make(Repository::class);

        /** @var array<int, string> $middleware */
        $middleware = $config->get('ai-sdk-toolbox.http.middleware', ['web', 'auth']);
        $prefix = $config->get('ai-sdk-toolbox.http.prefix', 'ai-toolbox');

        Route::middleware($middleware)
            ->prefix(is_string($prefix) ? $prefix : 'ai-toolbox')
            ->group(__DIR__.'/../routes/ai-toolbox.php');
    }
}
