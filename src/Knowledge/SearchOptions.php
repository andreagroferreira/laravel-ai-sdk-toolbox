<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

final readonly class SearchOptions
{
    public function __construct(
        public int $limit = 8,
        public float $minSimilarity = 0.0,
    ) {}
}
