<?php

declare(strict_types=1);

namespace AndreAgroFerreira\LaravelAiSdkToolbox\Tests;

use AndreAgroFerreira\LaravelAiSdkToolbox\LaravelAiSdkToolboxServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAiSdkToolboxServiceProvider::class,
        ];
    }
}
