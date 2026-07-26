<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\EmbeddedChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\Exceptions\QdrantException;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\QdrantStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.knowledge.stores.qdrant.url', 'http://qdrant.test:6333');
    config()->set('ai-sdk-toolbox.knowledge.stores.qdrant.collection', 'kb_test');
    Embeddings::fake();
});

function qdrantStore(): QdrantStore
{
    return new QdrantStore(app(Illuminate\Contracts\Config\Repository::class));
}

it('creates the collection when missing and upserts points with payload', function (): void {
    Http::fake([
        'qdrant.test:6333/collections/kb_test' => Http::sequence()
            ->push(['error' => 'not found'], 404)
            ->push(['result' => ['status' => 'completed']]),
        'qdrant.test:6333/collections/kb_test/points*' => Http::response(['result' => ['status' => 'completed']]),
    ]);

    qdrantStore()->upsert('docs', 42, [
        new EmbeddedChunk('Refunds in 30 days.', 0, array_fill(0, 1536, 0.1), ['path' => 'refunds.md']),
    ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/collections/kb_test')
        && is_array($request->data()['vectors'] ?? null)
        && ($request->data()['vectors']['distance'] ?? null) === 'Cosine');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/points')
        && $request['points'][0]['payload']['namespace'] === 'docs'
        && $request['points'][0]['payload']['document_id'] === 42
        && $request['points'][0]['payload']['content'] === 'Refunds in 30 days.');
});

it('deletes previous document points before upserting (atomic replace)', function (): void {
    Http::fake([
        'qdrant.test:6333/*' => Http::response(['result' => ['status' => 'completed']]),
    ]);

    qdrantStore()->upsert('docs', 42, [
        new EmbeddedChunk('New content.', 0, array_fill(0, 1536, 0.1), []),
    ]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_contains($request->url(), '/points/delete')
        && $request['filter']['must'][0]['key'] === 'document_id'
        && $request['filter']['must'][0]['match']['value'] === 42);
});

it('searches with a namespace filter and maps results', function (): void {
    Http::fake([
        'qdrant.test:6333/collections/kb_test' => Http::response(['result' => ['status' => 'ok']]),
        'qdrant.test:6333/collections/kb_test/points/search' => Http::response([
            'result' => [
                ['score' => 0.91, 'payload' => ['namespace' => 'docs', 'document_id' => 42, 'content' => 'Refunds in 30 days.', 'metadata' => ['path' => 'refunds.md']]],
                ['score' => 0.40, 'payload' => ['namespace' => 'docs', 'document_id' => 43, 'content' => 'Low score.', 'metadata' => ['path' => 'other.md']]],
            ],
        ]),
    ]);

    $results = qdrantStore()->search('docs', 'refund policy', new SearchOptions(limit: 5, minSimilarity: 0.5));

    expect($results)->toHaveCount(1)
        ->and($results->first()->content)->toBe('Refunds in 30 days.')
        ->and($results->first()->score)->toBe(0.91)
        ->and($results->first()->path)->toBe('refunds.md')
        ->and($results->first()->documentId)->toBe(42);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/points/search')
        && $request['filter']['must'][0]['key'] === 'namespace'
        && $request['filter']['must'][0]['match']['value'] === 'docs'
        && $request['limit'] === 5);
});

it('purges namespaces and deletes documents', function (): void {
    Http::fake([
        'qdrant.test:6333/*' => Http::response(['result' => ['status' => 'completed']]),
    ]);

    qdrantStore()->purge('docs');
    qdrantStore()->deleteDocument(42);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/points/delete')
        && $request['filter']['must'][0]['key'] === 'namespace');

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/points/delete')
        && $request['filter']['must'][0]['key'] === 'document_id');
});

it('sends the api key when configured', function (): void {
    config()->set('ai-sdk-toolbox.knowledge.stores.qdrant.api_key', 'secret-key');

    Http::fake([
        'qdrant.test:6333/collections/kb_test' => Http::response(['result' => ['status' => 'ok']]),
        'qdrant.test:6333/collections/kb_test/points/search' => Http::response(['result' => []]),
    ]);

    qdrantStore()->search('docs', 'x', new SearchOptions);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('api-key', 'secret-key'));
});

it('throws a QdrantException on failed operations', function (): void {
    Http::fake([
        'qdrant.test:6333/*' => Http::response(['error' => 'boom'], 500),
    ]);

    qdrantStore()->purge('docs');
})->throws(QdrantException::class, 'boom');
