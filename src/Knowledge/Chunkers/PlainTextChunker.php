<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunkers;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts\Chunker;

final class PlainTextChunker implements Chunker
{
    public function __construct(
        private readonly int $size = 1500,
        private readonly int $overlap = 200,
    ) {}

    public function chunk(string $content, array $metadata = []): array
    {
        $content = mb_trim($content);

        if ($content === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($content);
        $step = max(1, $this->size - $this->overlap);
        $index = 0;

        for ($offset = 0; $offset < $length; $offset += $step) {
            $piece = mb_trim(mb_substr($content, $offset, $this->size));

            if ($piece !== '') {
                $chunks[] = new Chunk($piece, $index++, $metadata);
            }
        }

        return $chunks;
    }
}
