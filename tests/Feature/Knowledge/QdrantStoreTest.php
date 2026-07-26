<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\EmbeddedChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\QdrantStore;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    if (getenv('KB_TEST_QDRANT') !== '1') {
        PHPUnit\Framework\Assert::markTestSkipped('Qdrant integration tests require KB_TEST_QDRANT=1 and a running Qdrant server.');
    }

    config()->set('ai-sdk-toolbox.knowledge.stores.qdrant.url', getenv('KB_TEST_QDRANT_URL') ?: 'http://127.0.0.1:6333');
    config()->set('ai-sdk-toolbox.knowledge.stores.qdrant.collection', 'kb_integration_'.Illuminate\Support\Str::random(6));

    Embeddings::fake(fn (): array => [qdrantVector(1.0, 0.1)]);
});

/**
 * A deterministic unit-ish vector so cosine scores are stable and positive.
 *
 * @return array<int, float>
 */
function qdrantVector(float $x, float $y): array
{
    $vector = array_fill(0, 1536, 0.0);
    $vector[0] = $x;
    $vector[1] = $y;

    return $vector;
}

function qdrantStoreFeature(): QdrantStore
{
    return new QdrantStore(app(Illuminate\Contracts\Config\Repository::class));
}

it('runs the full lifecycle against a real Qdrant', function (): void {
    $store = qdrantStoreFeature();

    $store->upsert('docs', 1, [
        new EmbeddedChunk('Refunds are allowed within 30 days.', 0, qdrantVector(1.0, 0.2), ['path' => 'refunds.md']),
        new EmbeddedChunk('Shipping is worldwide.', 1, qdrantVector(1.0, 0.2), ['path' => 'refunds.md']),
    ]);

    $results = $store->search('docs', 'refund policy', new SearchOptions(limit: 10));

    $first = $results->first();

    if (! $first instanceof AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult) {
        PHPUnit\Framework\Assert::fail('Expected results.');
    }

    expect($results)->toHaveCount(2)
        ->and($first->path)->toBe('refunds.md');

    expect($store->search('legal', 'refund policy', new SearchOptions(limit: 10)))->toBeEmpty();

    $store->upsert('docs', 1, [
        new EmbeddedChunk('New content only.', 0, qdrantVector(1.0, 0.2), ['path' => 'refunds.md']),
    ]);

    $results = $store->search('docs', 'content', new SearchOptions(limit: 10));

    $first = $results->first();

    if (! $first instanceof AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult) {
        PHPUnit\Framework\Assert::fail('Expected results.');
    }

    expect($results)->toHaveCount(1)
        ->and($first->content)->toBe('New content only.');

    $store->purge('docs');

    expect($store->search('docs', 'content', new SearchOptions(limit: 10)))->toBeEmpty();
});

it('deletes documents by id', function (): void {
    $store = qdrantStoreFeature();

    $store->upsert('docs', 99, [
        new EmbeddedChunk('Temporary document.', 0, qdrantVector(1.0, 0.2), []),
    ]);

    $store->deleteDocument(99);

    expect($store->search('docs', 'temporary', new SearchOptions(limit: 10)))->toBeEmpty();
});
