<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\Exceptions\QdrantException;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

/**
 * VectorStore backed by Qdrant (https://qdrant.tech) over its HTTP API.
 * One collection holds every namespace; isolation happens via payload filters.
 */
final class QdrantStore implements VectorStore
{
    private readonly string $baseUrl;

    private readonly string $collection;

    public function __construct(
        private readonly Repository $config,
    ) {
        $url = $config->get('ai-sdk-toolbox.knowledge.stores.qdrant.url', 'http://localhost:6333');
        $this->baseUrl = mb_rtrim(is_string($url) ? $url : 'http://localhost:6333', '/');

        $collection = $config->get('ai-sdk-toolbox.knowledge.stores.qdrant.collection', 'knowledge_chunks');
        $this->collection = is_string($collection) ? $collection : 'knowledge_chunks';
    }

    public function upsert(string $namespace, int $documentId, array $chunks): void
    {
        $this->ensureCollection();
        $this->deletePoints(['must' => [$this->match('document_id', $documentId)]]);

        if ($chunks === []) {
            return;
        }

        $response = $this->client()->put($this->collectionUrl('/points').'?wait=true', [
            'points' => array_map(
                fn ($chunk): array => [
                    'id' => (string) Str::uuid(),
                    'vector' => $chunk->embedding,
                    'payload' => [
                        'namespace' => $namespace,
                        'document_id' => $documentId,
                        'chunk_index' => $chunk->index,
                        'content' => $chunk->content,
                        'metadata' => $chunk->metadata,
                    ],
                ],
                $chunks,
            ),
        ]);

        if ($response->failed()) {
            throw QdrantException::requestFailed('upsert', $response->status(), $response->body());
        }
    }

    public function deleteDocument(int $documentId): void
    {
        $this->deletePoints(['must' => [$this->match('document_id', $documentId)]]);
    }

    /**
     * @return Collection<int, ChunkResult>
     */
    public function search(string $namespace, string $query, SearchOptions $options): Collection
    {
        $this->ensureCollection();

        $response = $this->client()->post($this->collectionUrl('/points/search'), [
            'vector' => $this->embedQuery($query),
            'limit' => $options->limit,
            'with_payload' => true,
            'filter' => ['must' => [$this->match('namespace', $namespace)]],
        ]);

        if ($response->failed()) {
            throw QdrantException::requestFailed('search', $response->status(), $response->body());
        }

        /** @var array<int, array{score?: mixed, payload?: mixed}> $results */
        $results = $response->json('result', []);

        return (new Collection($results))
            ->filter(fn (array $result): bool => is_array($result['payload'] ?? null))
            ->map(function (array $result): ?ChunkResult {
                $payload = $result['payload'];

                if (! is_string($payload['content'] ?? null)) {
                    return null;
                }

                /** @var array<string, mixed> $metadata */
                $metadata = [];

                if (is_array($payload['metadata'] ?? null)) {
                    foreach ($payload['metadata'] as $key => $value) {
                        if (is_string($key)) {
                            $metadata[$key] = $value;
                        }
                    }
                }

                $path = $metadata['path'] ?? '';

                return new ChunkResult(
                    content: $payload['content'],
                    score: is_numeric($result['score'] ?? null) ? (float) $result['score'] : 0.0,
                    documentId: is_int($payload['document_id'] ?? null) ? $payload['document_id'] : 0,
                    path: is_string($path) ? $path : '',
                    metadata: $metadata,
                );
            })
            ->filter()
            ->filter(fn (ChunkResult $result): bool => $result->score >= $options->minSimilarity)
            ->values();
    }

    public function purge(string $namespace): void
    {
        $this->deletePoints(['must' => [$this->match('namespace', $namespace)]]);
    }

    private function ensureCollection(): void
    {
        $response = $this->client()->get($this->collectionUrl());

        if ($response->successful()) {
            return;
        }

        $dimensions = $this->config->get('ai-sdk-toolbox.knowledge.embeddings.dimensions', 1536);

        $created = $this->client()->put($this->collectionUrl(), [
            'vectors' => [
                'size' => is_int($dimensions) ? $dimensions : 1536,
                'distance' => 'Cosine',
            ],
        ]);

        if ($created->failed()) {
            throw QdrantException::requestFailed('create collection', $created->status(), $created->body());
        }
    }

    /**
     * @param  array<string, mixed>  $filter
     */
    private function deletePoints(array $filter): void
    {
        $response = $this->client()->post($this->collectionUrl('/points/delete').'?wait=true', [
            'filter' => $filter,
        ]);

        if ($response->failed()) {
            throw QdrantException::requestFailed('delete points', $response->status(), $response->body());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function match(string $key, int|string $value): array
    {
        return ['key' => $key, 'match' => ['value' => $value]];
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

    private function collectionUrl(string $suffix = ''): string
    {
        return sprintf('/collections/%s%s', $this->collection, $suffix);
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)->acceptJson();

        $apiKey = $this->config->get('ai-sdk-toolbox.knowledge.stores.qdrant.api_key');

        if (is_string($apiKey) && $apiKey !== '') {
            $request = $request->withHeader('api-key', $apiKey);
        }

        return $request;
    }
}
