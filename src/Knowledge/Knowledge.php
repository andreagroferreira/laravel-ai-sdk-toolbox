<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

use Illuminate\Support\Collection;
use Laravel\Ai\Reranking;
use Throwable;

final class Knowledge
{
    public function __construct(
        private readonly VectorStore $store,
        private readonly string $namespace,
    ) {}

    public static function namespace(string $namespace = 'default'): self
    {
        return new self(app(VectorStore::class), $namespace);
    }

    /**
     * Search the namespace for chunks similar to the query.
     *
     * @return Collection<int, ChunkResult>
     */
    public function search(string $query, int $limit = 8, float $minSimilarity = 0.0, bool $rerank = false): Collection
    {
        $options = new SearchOptions(
            limit: $rerank ? $limit * 3 : $limit,
            minSimilarity: $minSimilarity,
        );

        $results = $this->store->search($this->namespace, $query, $options);

        if (! $rerank || $results->isEmpty()) {
            return $results;
        }

        return $this->rerank($query, $results)->take($limit)->values();
    }

    /**
     * @param  Collection<int, ChunkResult>  $results
     * @return Collection<int, ChunkResult>
     */
    private function rerank(string $query, Collection $results): Collection
    {
        try {
            $response = Reranking::of($results->map->content->values())
                ->limit($results->count())
                ->rerank($query);
        } catch (Throwable) {
            return $results;
        }

        return $response->collect()
            ->map(fn ($ranked): ?ChunkResult => $results->get($ranked->index))
            ->filter()
            ->values();
    }
}
