<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Plugins;

use AndreAgroFerreira\AiSdkToolbox\Plugins\Exceptions\PluginNotFoundException;
use Laravel\Ai\Contracts\Agent;

final class AgentRegistry
{
    /**
     * @var array<string, string>
     */
    private array $agents = [];

    public function register(string $name, string $class): void
    {
        $this->agents[$name] = $class;
    }

    public function unregister(string $name): void
    {
        unset($this->agents[$name]);
    }

    public function has(string $name): bool
    {
        return isset($this->agents[$name]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->agents;
    }

    public function make(string $name, mixed ...$args): Agent
    {
        $class = $this->agents[$name] ?? throw PluginNotFoundException::named('agent:'.$name);
        $agent = app($class, $args);

        if (! $agent instanceof Agent) {
            throw PluginNotFoundException::named(sprintf('agent:%s (class [%s] is not an AI agent)', $name, $class));
        }

        return $agent;
    }
}
