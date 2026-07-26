<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Contracts;

interface ProvidesSkillCapabilities
{
    /**
     * @return array<int, \Laravel\Ai\Contracts\Tool>
     */
    public function tools(): array;

    /**
     * @return array<int, object>
     */
    public function middleware(): array;
}
