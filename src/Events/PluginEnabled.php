<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class PluginEnabled
{
    use Dispatchable;

    public function __construct(
        public string $name,
    ) {}
}
