<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunk;

interface Chunker
{
    /**
     * Split extracted text into chunks.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<int, Chunk>
     */
    public function chunk(string $content, array $metadata = []): array;
}
