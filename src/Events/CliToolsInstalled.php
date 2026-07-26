<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class CliToolsInstalled
{
    use Dispatchable;

    /**
     * @param  array<int, \AndreAgroFerreira\AiSdkToolbox\CliTools\CliTool>  $tools
     */
    public function __construct(
        public array $tools,
        public string $source,
    ) {}
}
