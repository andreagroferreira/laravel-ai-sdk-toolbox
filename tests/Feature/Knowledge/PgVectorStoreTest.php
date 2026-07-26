<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\EmbeddedChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    if (getenv('KB_TEST_PGSQL') !== '1') {
        PHPUnit\Framework\Assert::markTestSkipped('pgvector integration tests require KB_TEST_PGSQL=1 and a PostgreSQL connection.');
    }
    config()->set('database.default', 'pgsql');
    config()->set('database.connections.pgsql', [
        'driver' => 'pgsql',
        'host' => getenv('KB_TEST_PGSQL_HOST') ?: '127.0.0.1',
        'port' => (int) (getenv('KB_TEST_PGSQL_PORT') ?: 5432),
        'database' => getenv('KB_TEST_PGSQL_DATABASE') ?: 'ai_toolbox_test',
        'username' => getenv('KB_TEST_PGSQL_USERNAME') ?: 'postgres',
        'password' => getenv('KB_TEST_PGSQL_PASSWORD') ?: 'postgres',
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ]);

    Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--realpath' => true, '--path' => __DIR__.'/../../database/migrations']);

    Embeddings::fake();
});

function pgDocument(string $path = 'refunds.md'): KnowledgeDocument
{
    return KnowledgeDocument::query()->create([
        'namespace' => 'docs',
        'source' => 'kb',
        'path' => $path,
        'hash' => hash('sha256', $path),
        'status' => 'pending',
    ]);
}

it('upserts and searches chunks with real pgvector', function (): void {
    $store = app(VectorStore::class);
    $document = pgDocument();

    $store->upsert('docs', $document->id, [
        new EmbeddedChunk('Refunds are allowed within 30 days.', 0, array_values(Embeddings::fakeEmbedding(1536)), ['path' => 'refunds.md']),
        new EmbeddedChunk('Shipping is worldwide.', 1, array_values(Embeddings::fakeEmbedding(1536)), ['path' => 'refunds.md']),
    ]);

    $results = $store->search('docs', 'refund policy', new SearchOptions(limit: 10));

    $first = $results->first();

    if (! $first instanceof AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult) {
        PHPUnit\Framework\Assert::fail('Expected results.');
    }

    expect($results)->toHaveCount(2)
        ->and($first->path)->toBe('refunds.md')
        ->and($first->score)->toBeFloat();
});

it('isolates namespaces', function (): void {
    $store = app(VectorStore::class);
    $document = pgDocument();

    $store->upsert('legal', $document->id, [
        new EmbeddedChunk('Terms and conditions.', 0, array_values(Embeddings::fakeEmbedding(1536)), []),
    ]);

    expect($store->search('docs', 'terms', new SearchOptions))->toBeEmpty()
        ->and($store->search('legal', 'terms', new SearchOptions))->toHaveCount(1);
});

it('replaces chunks atomically on re-upsert', function (): void {
    $store = app(VectorStore::class);
    $document = pgDocument();

    $store->upsert('docs', $document->id, [
        new EmbeddedChunk('Old content.', 0, array_values(Embeddings::fakeEmbedding(1536)), []),
    ]);

    $store->upsert('docs', $document->id, [
        new EmbeddedChunk('New content.', 0, array_values(Embeddings::fakeEmbedding(1536)), []),
    ]);

    $results = $store->search('docs', 'content', new SearchOptions);
    $first = $results->first();

    if (! $first instanceof AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult) {
        PHPUnit\Framework\Assert::fail('Expected results.');
    }

    expect($results)->toHaveCount(1)
        ->and($first->content)->toBe('New content.');
});

it('deletes documents and purges namespaces', function (): void {
    $store = app(VectorStore::class);
    $document = pgDocument();

    $store->upsert('docs', $document->id, [
        new EmbeddedChunk('Temporary.', 0, array_values(Embeddings::fakeEmbedding(1536)), []),
    ]);

    $store->deleteDocument($document->id);

    expect($store->search('docs', 'temporary', new SearchOptions))->toBeEmpty();

    $store->upsert('docs', $document->id, [
        new EmbeddedChunk('Temporary again.', 0, array_values(Embeddings::fakeEmbedding(1536)), []),
    ]);

    $store->purge('docs');

    expect($store->search('docs', 'temporary', new SearchOptions))->toBeEmpty();
});
