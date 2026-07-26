<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Testing;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\EmbeddedChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Illuminate\Support\Collection;

/**
 * In-memory VectorStore for tests and local development without pgvector.
 * Search returns the most recently upserted chunks, preserving insertion
 * order, limited by the search options.
 */
final class FakeVectorStore implements VectorStore
{
    /**
     * @var array<string, array<int, array<int, EmbeddedChunk>>>
     */
    private array $namespaces = [];

    /**
     * @param  array<int, EmbeddedChunk>  $chunks
     */
    public function upsert(string $namespace, int $documentId, array $chunks): void
    {
        $this->namespaces[$namespace][$documentId] = $chunks;
    }

    public function deleteDocument(int $documentId): void
    {
        foreach ($this->namespaces as $namespace => $documents) {
            unset($this->namespaces[$namespace][$documentId]);
        }
    }

    /**
     * @return Collection<int, ChunkResult>
     */
    public function search(string $namespace, string $query, SearchOptions $options): Collection
    {
        $results = new Collection;

        foreach ($this->namespaces[$namespace] ?? [] as $documentId => $chunks) {
            foreach ($chunks as $chunk) {
                $path = $chunk->metadata['path'] ?? '';

                $results->push(new ChunkResult(
                    content: $chunk->content,
                    score: 1.0,
                    documentId: $documentId,
                    path: is_string($path) ? $path : '',
                    metadata: $chunk->metadata,
                ));
            }
        }

        return $results->take($options->limit)->values();
    }

    public function purge(string $namespace): void
    {
        unset($this->namespaces[$namespace]);
    }
}
