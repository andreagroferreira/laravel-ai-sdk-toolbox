<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Tools;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Knowledge;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

final class KnowledgeSearch implements Tool
{
    public function __construct(
        private readonly string $namespace = 'default',
        private readonly int $limit = 8,
        private readonly float $minSimilarity = 0.0,
        private readonly bool $rerank = false,
        private readonly ?string $description = null,
    ) {}

    public function description(): string
    {
        return $this->description ?? sprintf(
            'Search the [%s] knowledge base for documents relevant to a question. Use this before answering domain-specific questions.',
            $this->namespace,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $query = $request['query'];

        if (! is_string($query) || mb_trim($query) === '') {
            return 'Error: the [query] argument must be a non-empty string.';
        }

        $results = Knowledge::namespace($this->namespace)->search(
            $query,
            limit: $this->limit,
            minSimilarity: $this->minSimilarity,
            rerank: $this->rerank,
        );

        if ($results->isEmpty()) {
            return sprintf('No relevant documents found in the [%s] knowledge base.', $this->namespace);
        }

        return $results
            ->map(fn (ChunkResult $result): string => sprintf(
                "---\n[path: %s | score: %.2f]\n%s",
                $result->path,
                $result->score,
                $result->content,
            ))
            ->implode("\n");
    }
}
