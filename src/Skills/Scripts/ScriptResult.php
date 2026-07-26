<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Skills\Scripts;

final readonly class ScriptResult
{
    public function __construct(
        public string $output,
        public string $errorOutput,
        public int $exitCode,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
