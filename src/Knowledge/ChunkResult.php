<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

final readonly class ChunkResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public float $score,
        public int $documentId,
        public string $path,
        public array $metadata = [],
    ) {}
}
