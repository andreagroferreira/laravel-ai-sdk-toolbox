<?php

declare(strict_types=1);

namespace AndreAgroFerreira\LaravelAiSdkToolbox;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

final class LaravelAiSdkToolboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-ai-sdk-toolbox.php', 'laravel-ai-sdk-toolbox');

        $this->app->singleton(LaravelAiSdkToolbox::class);
    }

    public function boot(): void
    {
        AboutCommand::add('LaravelAiSdkToolbox', fn (): array => ['Version' => LaravelAiSdkToolbox::VERSION]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-ai-sdk-toolbox.php' => config_path('laravel-ai-sdk-toolbox.php'),
            ], 'laravel-ai-sdk-toolbox-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-ai-sdk-toolbox-migrations');
        }
    }
}
