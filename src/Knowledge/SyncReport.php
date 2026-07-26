<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

final readonly class SyncReport
{
    /**
     * @param  array<int, string>  $failed
     */
    public function __construct(
        public string $source,
        public int $synced,
        public int $skipped,
        public int $deleted,
        public array $failed,
    ) {}
}
