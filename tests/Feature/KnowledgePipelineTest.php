<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Events\KnowledgeDocumentSynced;
use AndreAgroFerreira\AiSdkToolbox\Events\KnowledgeSyncFailed;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgePipeline;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSource;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Testing\FakeVectorStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    app()->instance(VectorStore::class, new FakeVectorStore);
    Embeddings::fake();
    Event::fake([KnowledgeDocumentSynced::class, KnowledgeSyncFailed::class]);

    config()->set('filesystems.disks.kb', ['driver' => 'local', 'root' => sys_get_temp_dir().'/kb-pipeline']);
    Storage::deleteDirectory('kb');
});

afterEach(function (): void {
    Storage::deleteDirectory('kb');
});

function pipelineSource(): KnowledgeSource
{
    return new KnowledgeSource('docs', 'kb', 'docs', 'docs', 'markdown', ['md']);
}

it('syncs a document end to end', function (): void {
    Storage::disk('kb')->put('docs/refunds.md', "# Refunds\n\nRefunds are allowed within 30 days.");

    $document = KnowledgeDocument::query()->create([
        'namespace' => 'docs',
        'source' => 'kb',
        'path' => 'refunds.md',
        'hash' => 'old',
        'status' => 'pending',
    ]);

    app(KnowledgePipeline::class)->syncDocument($document, pipelineSource());

    $document->refresh();

    $contents = Storage::disk('kb')->get('docs/refunds.md');

    if (! is_string($contents)) {
        PHPUnit\Framework\Assert::fail('Expected the fixture file to exist.');
    }

    expect($document->status)->toBe('synced')
        ->and($document->chunk_count)->toBe(1)
        ->and($document->synced_at)->not->toBeNull()
        ->and($document->hash)->toBe(hash('sha256', $contents));

    $results = app(VectorStore::class)->search('docs', 'refunds', new AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions);
    $first = $results->first();

    if (! $first instanceof AndreAgroFerreira\AiSdkToolbox\Knowledge\ChunkResult) {
        PHPUnit\Framework\Assert::fail('Expected at least one result.');
    }

    expect($results)->toHaveCount(1)
        ->and($first->content)->toContain('Refunds are allowed within 30 days')
        ->and($first->metadata['heading'])->toBe('Refunds');

    Event::assertDispatched(KnowledgeDocumentSynced::class);
});

it('marks the document as error when the extension is unsupported', function (): void {
    Storage::disk('kb')->put('docs/data.bin', 'binary');

    $document = KnowledgeDocument::query()->create([
        'namespace' => 'docs',
        'source' => 'kb',
        'path' => 'data.bin',
        'hash' => 'old',
        'status' => 'pending',
    ]);

    try {
        app(KnowledgePipeline::class)->syncDocument($document, pipelineSource());
    } catch (RuntimeException) {
        // expected
    }

    $document->refresh();

    expect($document->status)->toBe('error')
        ->and($document->error)->toContain('No extractor');

    Event::assertDispatched(KnowledgeSyncFailed::class);
});

it('replaces chunks on re-sync', function (): void {
    Storage::disk('kb')->put('docs/policy.md', "# V1\n\nFirst version.");

    $document = KnowledgeDocument::query()->create([
        'namespace' => 'docs',
        'source' => 'kb',
        'path' => 'policy.md',
        'hash' => 'old',
        'status' => 'pending',
    ]);

    $pipeline = app(KnowledgePipeline::class);
    $pipeline->syncDocument($document, pipelineSource());

    Storage::disk('kb')->put('docs/policy.md', "# V2\n\nSecond version. ".str_repeat('More content here. ', 200));
    $pipeline->syncDocument($document->refresh(), pipelineSource());

    $results = app(VectorStore::class)->search('docs', 'version', new AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions);

    expect($results->pluck('content')->implode(' '))->not->toContain('First version')
        ->and($document->chunk_count)->toBeGreaterThan(1);
});

it('deletes a document and its chunks', function (): void {
    Storage::disk('kb')->put('docs/gone.md', "# Gone\n\nSoon deleted.");

    $document = KnowledgeDocument::query()->create([
        'namespace' => 'docs',
        'source' => 'kb',
        'path' => 'gone.md',
        'hash' => 'old',
        'status' => 'pending',
    ]);

    $pipeline = app(KnowledgePipeline::class);
    $pipeline->syncDocument($document, pipelineSource());
    $pipeline->deleteDocument($document);

    expect(KnowledgeDocument::query()->find($document->id))->toBeNull()
        ->and(app(VectorStore::class)->search('docs', 'gone', new AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions))->toBeEmpty();
});
