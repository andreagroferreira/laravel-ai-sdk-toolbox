<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\Remember;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

final class RememberMiddleware
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        return $next($prompt);
    }
}
