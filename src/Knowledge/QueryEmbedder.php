<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

use Illuminate\Contracts\Config\Repository;
use Laravel\Ai\Embeddings;

final readonly class QueryEmbedder
{
    public function __construct(
        private Repository $config,
    ) {}

    /**
     * @return array<int, float>
     */
    public function embed(string $query, ?int $timeout): array
    {
        /** @var array<string, mixed> $config */
        $config = $this->config->get('ai-sdk-toolbox.knowledge.embeddings', []);

        $response = Embeddings::for([$query])
            ->timeout(SearchTimeouts::resolve('embeddings', $timeout))
            ->cache()
            ->generate(
                is_string($config['provider'] ?? null) ? $config['provider'] : null,
                is_string($config['model'] ?? null) ? $config['model'] : null,
            );

        return array_values($response->embeddings[0]);
    }
}
