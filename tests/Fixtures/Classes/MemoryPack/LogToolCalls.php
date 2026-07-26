<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\MemoryPack;

use Laravel\Ai\Events\ToolInvoked;

final class LogToolCalls
{
    /**
     * @var array<int, string>
     */
    public static array $handled = [];

    public function handle(ToolInvoked $event): void
    {
        self::$handled[] = $event::class;
    }
}
