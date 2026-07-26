<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests;

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolboxServiceProvider;
use AndreAgroFerreira\AiSdkToolbox\Skills\Security\SkillLock;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        File::delete(sys_get_temp_dir().'/ai-toolbox-tests.lock');

        app()->instance(SkillLock::class, new SkillLock(sys_get_temp_dir().'/ai-toolbox-tests.lock'));
    }

    protected function tearDown(): void
    {
        File::delete(sys_get_temp_dir().'/ai-toolbox-tests.lock');

        parent::tearDown();
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Ai\AiServiceProvider::class,
            AiSdkToolboxServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
}
