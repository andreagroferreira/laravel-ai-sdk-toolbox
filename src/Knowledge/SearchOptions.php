<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

final readonly class SearchOptions
{
    /**
     * @param  int|null  $timeout  Seconds this search may wait at each network call; null uses the configured value. Custom stores should honour it.
     */
    public function __construct(
        public int $limit = 8,
        public float $minSimilarity = 0.0,
        public ?int $timeout = null,
    ) {}
}
