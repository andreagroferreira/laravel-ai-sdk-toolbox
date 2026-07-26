<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Events;

use AndreAgroFerreira\AiSdkToolbox\Plugins\Plugin;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class PluginInstalled
{
    use Dispatchable;

    public function __construct(
        public Plugin $plugin,
        public string $source,
    ) {}
}
