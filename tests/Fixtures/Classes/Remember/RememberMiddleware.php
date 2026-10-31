<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Tests\Fixtures\Classes\Remember;

use Closure;
use Laravel\Ai\PendingStep;

final class RememberMiddleware
{
    /**
     * @var array<int, PendingStep>
     */
    public static array $steps = [];

    public function handle(PendingStep $step, Closure $next): mixed
    {
        self::$steps[] = $step;

        return $next($step);
    }
}
