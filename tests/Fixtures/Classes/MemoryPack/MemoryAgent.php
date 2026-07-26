<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\MemoryPack;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

final class MemoryAgent implements Agent
{
    use Promptable;

    public function __construct(
        public readonly string $scope = 'default',
    ) {}

    public function instructions(): string
    {
        return 'You are the memory agent.';
    }
}
