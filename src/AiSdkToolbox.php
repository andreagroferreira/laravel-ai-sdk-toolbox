<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox;

use AndreAgroFerreira\AiSdkToolbox\Management\CliToolManager;
use AndreAgroFerreira\AiSdkToolbox\Management\SkillManager;
use AndreAgroFerreira\AiSdkToolbox\Plugins\AgentRegistry;
use AndreAgroFerreira\AiSdkToolbox\Plugins\PluginManager;
use Closure;

final class AiSdkToolbox
{
    public const string VERSION = '0.1.0';

    /**
     * @var (Closure(mixed): bool)|null
     */
    private static ?Closure $authorizationCallback = null;

    /**
     * Register the callback that determines who may manage skills and CLI
     * tools over HTTP. When no callback is registered, access is only
     * granted in the local environment.
     *
     * @param  Closure(mixed): bool  $callback
     */
    public static function authorize(Closure $callback): void
    {
        self::$authorizationCallback = $callback;
    }

    public static function check(mixed $user): bool
    {
        if (self::$authorizationCallback instanceof Closure) {
            return (bool) (self::$authorizationCallback)($user);
        }

        return app()->environment('local');
    }

    /**
     * Reset the authorization callback. Intended for tests.
     */
    public static function flushAuthorization(): void
    {
        self::$authorizationCallback = null;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function skills(): SkillManager
    {
        return app(SkillManager::class);
    }

    public function cliTools(): CliToolManager
    {
        return app(CliToolManager::class);
    }

    public function plugins(): PluginManager
    {
        return app(PluginManager::class);
    }

    public function agents(): AgentRegistry
    {
        return app(AgentRegistry::class);
    }
}
