<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\AiSdkToolbox;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\EmbeddedChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Testing\FakeVectorStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;

beforeEach(function (): void {
    config()->set('ai-sdk-toolbox.http.enabled', true);
    config()->set('ai-sdk-toolbox.knowledge.sources', [
        'docs' => ['disk' => 'kb', 'path' => 'docs', 'namespace' => 'docs', 'chunker' => 'markdown', 'extensions' => ['md']],
    ]);
    config()->set('filesystems.disks.kb', ['driver' => 'local', 'root' => sys_get_temp_dir().'/ai-http-kb']);

    app()->instance(VectorStore::class, new FakeVectorStore);
    Embeddings::fake();

    File::deleteDirectory(sys_get_temp_dir().'/ai-http-kb');
    Storage::disk('kb')->put('docs/refunds.md', "# Refunds\n\n30 days.");

    AiSdkToolbox::authorize(fn (): bool => true);
});

afterEach(function (): void {
    AiSdkToolbox::flushAuthorization();
    File::deleteDirectory(sys_get_temp_dir().'/ai-http-kb');
});

it('lists knowledge documents with filters', function (): void {
    $this->postJson('/ai-toolbox/knowledge/sync', ['inline' => true]);

    $this->getJson('/ai-toolbox/knowledge/documents')
        ->assertOk()
        ->assertJsonPath('data.0.namespace', 'docs')
        ->assertJsonPath('data.0.path', 'refunds.md')
        ->assertJsonPath('data.0.status', 'synced');

    $this->getJson('/ai-toolbox/knowledge/documents?namespace=other')
        ->assertOk()
        ->assertJsonPath('data', []);

    $this->getJson('/ai-toolbox/knowledge/documents?status=error')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('syncs knowledge sources over HTTP', function (): void {
    $this->postJson('/ai-toolbox/knowledge/sync', ['inline' => true])
        ->assertOk()
        ->assertJsonPath('data.0.source', 'docs')
        ->assertJsonPath('data.0.synced', 1);

    $this->postJson('/ai-toolbox/knowledge/sync', ['source' => 'missing'])
        ->assertNotFound();
});

it('searches the knowledge base over HTTP', function (): void {
    app(VectorStore::class)->upsert('docs', 1, [
        new EmbeddedChunk('Refunds are allowed within 30 days.', 0, [0.1], ['path' => 'refunds.md']),
    ]);

    $this->getJson('/ai-toolbox/knowledge/search?namespace=docs&query=refund+policy')
        ->assertOk()
        ->assertJsonPath('data.0.path', 'refunds.md');

    $this->getJson('/ai-toolbox/knowledge/search?namespace=docs')
        ->assertStatus(422);
});

it('reports knowledge status per namespace', function (): void {
    $this->postJson('/ai-toolbox/knowledge/sync', ['inline' => true]);

    $this->getJson('/ai-toolbox/knowledge/status')
        ->assertOk()
        ->assertJsonPath('data.0.namespace', 'docs')
        ->assertJsonPath('data.0.documents', 1)
        ->assertJsonPath('data.0.synced', 1)
        ->assertJsonPath('data.0.errors', 0);
});
