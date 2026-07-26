<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\Remember;

use AndreAgroFerreira\AiSdkToolbox\Skills\Contracts\ProvidesSkillCapabilities;

final class RememberProvider implements ProvidesSkillCapabilities
{
    /**
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    public function tools(): array
    {
        return [new SaveMemoryTool];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RememberMiddleware];
    }
}
