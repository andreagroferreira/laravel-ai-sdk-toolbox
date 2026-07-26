<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

final class PgVectorStore implements VectorStore
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function upsert(string $namespace, int $documentId, array $chunks): void
    {
        DB::transaction(function () use ($namespace, $documentId, $chunks): void {
            KnowledgeChunk::query()
                ->where('namespace', $namespace)
                ->where('document_id', $documentId)
                ->delete();

            $now = now();

            KnowledgeChunk::query()->insert(
                array_map(
                    fn ($chunk): array => [
                        'namespace' => $namespace,
                        'document_id' => $documentId,
                        'chunk_index' => $chunk->index,
                        'content' => $chunk->content,
                        'embedding' => json_encode($chunk->embedding),
                        'metadata' => json_encode($chunk->metadata),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                    $chunks,
                ),
            );
        });
    }

    public function deleteDocument(int $documentId): void
    {
        KnowledgeChunk::query()->where('document_id', $documentId)->delete();
    }

    /**
     * @return Collection<int, ChunkResult>
     */
    public function search(string $namespace, string $query, SearchOptions $options): Collection
    {
        $embedding = $this->embedQuery($query);

        /** @var Collection<int, KnowledgeChunk> $chunks */
        $chunks = KnowledgeChunk::query()
            ->where('namespace', $namespace)
            ->selectVectorDistance('embedding', $embedding, as: 'distance')
            ->whereVectorDistanceLessThan('embedding', $embedding, 1.0 - $options->minSimilarity)
            ->orderByVectorDistance('embedding', $embedding)
            ->limit($options->limit)
            ->get();

        $documents = KnowledgeDocument::query()
            ->whereIn('id', $chunks->pluck('document_id')->unique()->all())
            ->pluck('path', 'id');

        return $chunks->map(function (KnowledgeChunk $chunk) use ($documents): ChunkResult {
            $distance = $chunk->getAttribute('distance');
            $path = $documents[$chunk->document_id] ?? '';

            return new ChunkResult(
                content: $chunk->content,
                score: is_numeric($distance) ? 1.0 - (float) $distance : 0.0,
                documentId: $chunk->document_id,
                path: is_string($path) ? $path : '',
                metadata: $chunk->metadata ?? [],
            );
        })->values();
    }

    public function purge(string $namespace): void
    {
        KnowledgeChunk::query()->where('namespace', $namespace)->delete();
    }

    /**
     * @return array<int, float>
     */
    private function embedQuery(string $query): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->config->get('ai-sdk-toolbox.knowledge.embeddings', []);

        $response = Embeddings::for([$query])
            ->cache()
            ->generate(
                is_string($config['provider'] ?? null) ? $config['provider'] : null,
                is_string($config['model'] ?? null) ? $config['model'] : null,
            );

        return array_values($response->embeddings[0]);
    }
}
