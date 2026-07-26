<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

final readonly class EmbeddedChunk
{
    /**
     * @param  array<int, float>  $embedding
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public int $index,
        public array $embedding,
        public array $metadata = [],
    ) {}
}
