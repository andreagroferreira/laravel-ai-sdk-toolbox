<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

use Illuminate\Support\Collection;

interface VectorStore
{
    /**
     * Replace all chunks of a document with the given embedded ones.
     * Implementations must be atomic: readers never see a partially
     * updated document.
     *
     * @param  array<int, EmbeddedChunk>  $chunks
     */
    public function upsert(string $namespace, int $documentId, array $chunks): void;

    public function deleteDocument(int $documentId): void;

    /**
     * Search for chunks similar to the plain-text query. The store embeds
     * the query internally.
     *
     * @return Collection<int, ChunkResult>
     */
    public function search(string $namespace, string $query, SearchOptions $options): Collection;

    public function purge(string $namespace): void;
}
