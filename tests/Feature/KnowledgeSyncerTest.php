<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Jobs\SyncKnowledgeDocument;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSyncer;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Testing\FakeVectorStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

function docsSource(KnowledgeSyncer $syncer): AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSource
{
    $source = $syncer->source('docs');

    if (! $source instanceof AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSource) {
        PHPUnit\Framework\Assert::fail('The docs source is not configured.');
    }

    return $source;
}

beforeEach(function (): void {
    app()->instance(VectorStore::class, new FakeVectorStore);
    Embeddings::fake();

    config()->set('filesystems.disks.kb', ['driver' => 'local', 'root' => sys_get_temp_dir().'/kb-sync']);
    config()->set('ai-sdk-toolbox.knowledge.sources', [
        'docs' => ['disk' => 'kb', 'path' => 'docs', 'namespace' => 'docs', 'chunker' => 'markdown', 'extensions' => ['md', 'bin']],
    ]);

    Storage::deleteDirectory('kb');
    Storage::disk('kb')->put('docs/refunds.md', "# Refunds\n\n30 days.");
    Storage::disk('kb')->put('docs/shipping.md', "# Shipping\n\nWorldwide.");
});

afterEach(function (): void {
    Storage::deleteDirectory('kb');
});

it('syncs new documents inline and skips unchanged ones on the second run', function (): void {
    $syncer = app(KnowledgeSyncer::class);

    $first = $syncer->sync(docsSource($syncer), inline: true);

    expect($first->synced)->toBe(2)
        ->and($first->skipped)->toBe(0)
        ->and(KnowledgeDocument::query()->count())->toBe(2);

    $second = $syncer->sync(docsSource($syncer), inline: true);

    expect($second->synced)->toBe(0)
        ->and($second->skipped)->toBe(2);
});

it('re-syncs modified documents', function (): void {
    $syncer = app(KnowledgeSyncer::class);
    $syncer->sync(docsSource($syncer), inline: true);

    Storage::disk('kb')->put('docs/refunds.md', "# Refunds\n\nNow 60 days.");

    $report = $syncer->sync(docsSource($syncer), inline: true);

    expect($report->synced)->toBe(1)
        ->and($report->skipped)->toBe(1);

    $results = app(VectorStore::class)->search('docs', 'days', new AndreAgroFerreira\AiSdkToolbox\Knowledge\SearchOptions);

    expect($results->pluck('content')->implode(' '))->toContain('60 days');
});

it('deletes documents that disappeared from the disk', function (): void {
    $syncer = app(KnowledgeSyncer::class);
    $syncer->sync(docsSource($syncer), inline: true);

    Storage::disk('kb')->delete('docs/shipping.md');

    $report = $syncer->sync(docsSource($syncer), inline: true);

    expect($report->deleted)->toBe(1)
        ->and(KnowledgeDocument::query()->where('path', 'shipping.md')->exists())->toBeFalse();
});

it('dispatches queue jobs when not inline', function (): void {
    Bus::fake([SyncKnowledgeDocument::class]);

    $report = app(KnowledgeSyncer::class)->sync(docsSource(app(KnowledgeSyncer::class)), inline: false);

    expect($report->synced)->toBe(2);

    Bus::assertDispatched(SyncKnowledgeDocument::class, 2);
});

it('runs the kb-sync and kb-status commands', function (): void {
    expect(Artisan::call('ai:kb-sync', ['source' => 'docs', '--sync' => true]))->toBe(0)
        ->and(Artisan::call('ai:kb-status'))->toBe(0)
        ->and(Artisan::call('ai:kb-sync', ['source' => 'missing']))->toBe(1);
});
