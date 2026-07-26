<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunkers;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts\Chunker;

/**
 * Markdown-aware chunker: splits at heading boundaries first, then applies
 * the plain-text strategy to oversized sections. Each chunk carries the
 * nearest heading in its metadata.
 */
final class MarkdownChunker implements Chunker
{
    public function __construct(
        private readonly int $size = 1500,
        private readonly int $overlap = 200,
    ) {}

    public function chunk(string $content, array $metadata = []): array
    {
        $sections = $this->sections($content);
        $chunks = [];
        $index = 0;
        $textChunker = new PlainTextChunker($this->size, $this->overlap);

        foreach ($sections as $section) {
            foreach ($textChunker->chunk($section['body']) as $chunk) {
                $chunks[] = new Chunk(
                    $chunk->content,
                    $index++,
                    [...$metadata, ...$chunk->metadata, 'heading' => $section['heading']],
                );
            }
        }

        return $chunks;
    }

    /**
     * @return array<int, array{heading: string|null, body: string}>
     */
    private function sections(string $content): array
    {
        $parts = preg_split('/^(#{1,6}\s+.+)$/m', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [['heading' => null, 'body' => $content]];
        }

        $sections = [];
        $heading = null;
        $buffer = '';

        foreach ($parts as $part) {
            if (preg_match('/^#{1,6}\s+(.+)$/', $part, $matches) === 1) {
                if (mb_trim($buffer) !== '') {
                    $sections[] = ['heading' => $heading, 'body' => $buffer];
                }

                $heading = mb_trim($matches[1]);
                $buffer = $part."\n";
            } else {
                $buffer .= $part;
            }
        }

        if (mb_trim($buffer) !== '') {
            $sections[] = ['heading' => $heading, 'body' => $buffer];
        }

        return $sections;
    }
}
