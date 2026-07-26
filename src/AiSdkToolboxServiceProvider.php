<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox;

use AndreAgroFerreira\AiSdkToolbox\CliTools\CliToolRegistry;
use AndreAgroFerreira\AiSdkToolbox\Console\CliToolListCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\KnowledgeStatusCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\KnowledgeSyncCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\PluginDisableCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\PluginEnableCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\PluginInstallCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\PluginListCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\PluginRemoveCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillAuditCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillInstallCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillListCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillMakeCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillRemoveCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillShowCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillTrustCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillUpdateCommand;
use AndreAgroFerreira\AiSdkToolbox\Console\SkillVerifyCommand;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors\ExtractorRegistry;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\PgVectorStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\QdrantStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use AndreAgroFerreira\AiSdkToolbox\Plugins\AgentRegistry;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManager;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginRegistry;
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
        $this->app->singleton(ExtractorRegistry::class);
        $this->app->singleton(VectorStore::class, $this->vectorStoreResolver(...));
        $this->app->singleton(AgentRegistry::class);
        $this->app->singleton(PluginRegistry::class, fn (): PluginRegistry => new PluginRegistry(
            base_path('ai-plugins.lock'),
            new Plugins\PluginManifest,
            $this->app->make(SkillRegistry::class),
            $this->app->make(AgentRegistry::class),
        ));
    }

    public function boot(): void
    {
        AboutCommand::add('AiSdkToolbox', fn (): array => ['Version' => AiSdkToolbox::VERSION]);

        $this->bootPlugins();
        $this->registerHttpRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-sdk-toolbox.php' => config_path('ai-sdk-toolbox.php'),
            ], 'ai-sdk-toolbox-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ai-sdk-toolbox-migrations');

            $this->commands([
                CliToolListCommand::class,
                KnowledgeStatusCommand::class,
                KnowledgeSyncCommand::class,
                PluginDisableCommand::class,
                PluginEnableCommand::class,
                PluginInstallCommand::class,
                PluginListCommand::class,
                PluginRemoveCommand::class,
                SkillAuditCommand::class,
                SkillInstallCommand::class,
                SkillListCommand::class,
                SkillMakeCommand::class,
                SkillRemoveCommand::class,
                SkillShowCommand::class,
                SkillTrustCommand::class,
                SkillUpdateCommand::class,
                SkillVerifyCommand::class,
            ]);
        }
    }

    private function bootPlugins(): void
    {
        /** @var PluginManager $manager */
        $manager = $this->app->make(PluginManager::class);

        $manager->installComposerPlugins();

        /** @var PluginRegistry $registry */
        $registry = $this->app->make(PluginRegistry::class);
        $registry->bootEnabled();
    }

    private function vectorStoreResolver(): VectorStore
    {
        /** @var Repository $config */
        $config = $this->app->make(Repository::class);

        return match ($config->get('ai-sdk-toolbox.knowledge.store', 'pgvector')) {
            'qdrant' => new QdrantStore($config),
            default => new PgVectorStore($config),
        };
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
