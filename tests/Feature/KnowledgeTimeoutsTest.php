<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\EmbeddedChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Knowledge;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\PgVectorStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Stores\QdrantStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Testing\FakeVectorStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Tools\KnowledgeSearch;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\RankedDocument;
use Laravel\Ai\Responses\Data\RerankingUsage;
use Laravel\Ai\Responses\RerankingResponse;
use Laravel\Ai\Tools\Request as ToolRequest;

function seedOrderedKnowledge(): void
{
    $store = new FakeVectorStore;

    $store->upsert('docs', 1, [
        new EmbeddedChunk('first', 0, [0.1], ['path' => 'a.md']),
        new EmbeddedChunk('second', 1, [0.2], ['path' => 'a.md']),
    ]);

    app()->instance(VectorStore::class, $store);
}

it('keeps the store order without reranking when no timeout is given', function (): void {
    seedOrderedKnowledge();

    $contents = Knowledge::namespace('docs')->search('anything')->map->content->all();

    expect($contents)->toBe(['first', 'second']);
});

it('keeps the reranker order when no timeout is given', function (): void {
    seedOrderedKnowledge();

    Reranking::fake([
        new RerankingResponse([
            new RankedDocument(1, 'second', 0.9),
            new RankedDocument(0, 'first', 0.1),
        ], new RerankingUsage, new Meta),
    ]);

    $contents = Knowledge::namespace('docs')->search('anything', rerank: true)->map->content->all();

    expect($contents)->toBe(['second', 'first']);
});

/**
 * @return ArrayObject<string, mixed>
 */
function fakeSearchProviders(int $rerankStatus = 200, string $rerankBody = ''): ArrayObject
{
    /** @var ArrayObject<string, mixed> $timeouts */
    $timeouts = new ArrayObject;

    config()->set('ai.providers.openai.key', 'test-key');
    config()->set('ai.providers.cohere.key', 'test-key');
    config()->set('ai.default_for_reranking', 'cohere');
    config()->set('ai-sdk-toolbox.knowledge.embeddings.provider', 'openai');
    config()->set('ai-sdk-toolbox.knowledge.stores.qdrant.url', 'http://qdrant.test:6333');
    config()->set('ai-sdk-toolbox.knowledge.stores.qdrant.collection', 'kb_test');

    Http::fake(function (Request $request, array $options) use ($timeouts, $rerankStatus, $rerankBody) {
        $url = $request->url();

        if (str_contains($url, 'api.openai.com')) {
            $timeouts['embeddings'] = $options['timeout'] ?? null;

            return Http::response(['data' => [['embedding' => [0.1, 0.2]]], 'usage' => ['prompt_tokens' => 1]]);
        }

        if (str_contains($url, 'api.cohere.com')) {
            $timeouts['reranking'] = $options['timeout'] ?? null;

            return $rerankStatus === 200
                ? Http::response(['results' => [['index' => 0, 'relevance_score' => 0.9]], 'meta' => []])
                : Http::response($rerankBody, $rerankStatus);
        }

        if (str_contains($url, '/points/search')) {
            $timeouts['store-search'] = $options['timeout'] ?? null;

            return Http::response(['result' => [[
                'score' => 0.9,
                'payload' => ['content' => 'first', 'document_id' => 1, 'path' => 'a.md', 'metadata' => []],
            ]]]);
        }

        if (str_contains($url, '/points')) {
            $timeouts['store-upsert'] = $options['timeout'] ?? null;
        }

        return Http::response(['result' => ['status' => 'completed']]);
    });

    return $timeouts;
}

function qdrantSearch(?int $timeout = null): void
{
    app()->instance(VectorStore::class, new QdrantStore(app(Repository::class)));

    Knowledge::namespace('docs')->search('anything', rerank: true, timeout: $timeout);
}

it('asks for reranking with 120 seconds by default and the configured value otherwise', function (): void {
    seedOrderedKnowledge();
    $timeouts = fakeSearchProviders();

    Knowledge::namespace('docs')->search('anything', rerank: true);
    expect($timeouts['reranking'])->toBe(120);

    config()->set('ai-sdk-toolbox.knowledge.timeouts.reranking', 300);
    Knowledge::namespace('docs')->search('anything', rerank: true);
    expect($timeouts['reranking'])->toBe(300);
});

it('asks for the query embedding with the embeddings timeout', function (): void {
    $timeouts = fakeSearchProviders();

    qdrantSearch();
    expect($timeouts['embeddings'])->toBe(120);

    config()->set('ai-sdk-toolbox.knowledge.timeouts.embeddings', 240);
    Cache::flush();
    qdrantSearch();
    expect($timeouts['embeddings'])->toBe(240);
});

it('asks pgvector for the query embedding with the embeddings timeout', function (?int $configured, ?int $perCall, int $expected): void {
    $timeouts = fakeSearchProviders();

    if ($configured !== null) {
        config()->set('ai-sdk-toolbox.knowledge.timeouts.embeddings', $configured);
    }

    // The test database has no vector support: the embedding request comes first, then the query fails.
    rescue(fn () => (new PgVectorStore(app(Repository::class)))->search('docs', 'anything', new SearchOptions(timeout: $perCall)), report: false);

    expect($timeouts['embeddings'])->toBe($expected);
})->with([
    'default' => [null, null, 120],
    'configured' => [240, null, 240],
    'per call' => [240, 600, 600],
]);

it('applies the store timeout to the qdrant search and not to the upsert', function (): void {
    $timeouts = fakeSearchProviders();

    qdrantSearch();
    expect($timeouts['store-search'])->toBe(120);

    config()->set('ai-sdk-toolbox.knowledge.timeouts.store', 180);
    qdrantSearch();
    expect($timeouts['store-search'])->toBe(180);

    app(VectorStore::class)->upsert('docs', 1, [new EmbeddedChunk('first', 0, [0.1, 0.2], ['path' => 'a.md'])]);
    expect($timeouts['store-upsert'])->toBe(30);
});

it('uses the per-call timeout in the three stages', function (): void {
    $timeouts = fakeSearchProviders();

    qdrantSearch(timeout: 600);

    expect($timeouts['embeddings'])->toBe(600)
        ->and($timeouts['store-search'])->toBe(600)
        ->and($timeouts['reranking'])->toBe(600);
});

it('passes the tool timeout to the search', function (): void {
    $timeouts = fakeSearchProviders();
    app()->instance(VectorStore::class, new QdrantStore(app(Repository::class)));

    (new KnowledgeSearch('docs', rerank: true, timeout: 45))->handle(new ToolRequest(['query' => 'anything']));

    expect($timeouts['embeddings'])->toBe(45)
        ->and($timeouts['store-search'])->toBe(45)
        ->and($timeouts['reranking'])->toBe(45);
});

it('logs a failed reranking without its message or the query and keeps the vector order', function (): void {
    seedOrderedKnowledge();
    fakeSearchProviders(500, 'provider rejected: secret question');
    /** @var ArrayObject<int, MessageLogged> $logged */
    $logged = new ArrayObject;
    Event::listen(MessageLogged::class, fn (MessageLogged $message) => $logged->append($message));

    $contents = Knowledge::namespace('docs')->search('secret question', rerank: true)->map->content->all();

    expect($contents)->toBe(['first', 'second'])
        ->and($logged)->toHaveCount(1);

    $warning = $logged[0] ?? throw new RuntimeException('Nothing was logged.');
    $exception = $warning->context['exception'] ?? null;

    expect($warning->level)->toBe('warning')
        ->and($warning->context['namespace'] ?? null)->toBe('docs')
        ->and($exception)->toBe(RequestException::class)
        ->and(str_contains($warning->message.json_encode($warning->context), 'secret question'))->toBeFalse();
});

it('falls back to 120 seconds for a timeout that is not a positive integer', function (mixed $configured): void {
    seedOrderedKnowledge();
    $timeouts = fakeSearchProviders();
    config()->set('ai-sdk-toolbox.knowledge.timeouts.reranking', $configured);

    Knowledge::namespace('docs')->search('anything', rerank: true);

    expect($timeouts['reranking'])->toBe(120);
})->with([0, -5, 'abc', null, 1.5, true]);

it('falls back to 120 seconds for a per-call timeout that is not positive', function (int $timeout): void {
    seedOrderedKnowledge();
    $timeouts = fakeSearchProviders();
    config()->set('ai-sdk-toolbox.knowledge.timeouts.reranking', 300);

    Knowledge::namespace('docs')->search('anything', rerank: true, timeout: $timeout);

    expect($timeouts['reranking'])->toBe(120);
})->with([0, -5]);

it('accepts a numeric string from the environment', function (): void {
    seedOrderedKnowledge();
    $timeouts = fakeSearchProviders();
    config()->set('ai-sdk-toolbox.knowledge.timeouts.reranking', '300');

    Knowledge::namespace('docs')->search('anything', rerank: true);

    expect($timeouts['reranking'])->toBe(300);
});
