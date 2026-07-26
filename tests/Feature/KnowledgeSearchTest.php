<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\EmbeddedChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Testing\FakeVectorStore;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Tools\KnowledgeSearch;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\VectorStore;
use Laravel\Ai\Tools\Request;

beforeEach(function (): void {
    app()->instance(VectorStore::class, new FakeVectorStore);
});

function seedKnowledge(): void
{
    app(VectorStore::class)->upsert('docs', 1, [
        new EmbeddedChunk('Refunds are allowed within 30 days of purchase.', 0, [0.1], ['path' => 'refunds.md', 'heading' => 'Refund Policy']),
        new EmbeddedChunk('Digital goods are not refundable after download.', 1, [0.2], ['path' => 'refunds.md', 'heading' => 'Digital Goods']),
    ]);
}

it('answers with the matching chunks', function (): void {
    seedKnowledge();

    $result = (new KnowledgeSearch(namespace: 'docs'))->handle(new Request(['query' => 'can I get a refund?']));

    expect((string) $result)->toContain('Refunds are allowed within 30 days')
        ->and((string) $result)->toContain('refunds.md')
        ->and((string) $result)->toContain('score:');
});

it('reports when nothing matches', function (): void {
    $result = (new KnowledgeSearch(namespace: 'docs'))->handle(new Request(['query' => 'anything']));

    expect((string) $result)->toContain('No relevant documents found');
});

it('rejects empty queries', function (): void {
    $result = (new KnowledgeSearch)->handle(new Request(['query' => '  ']));

    expect((string) $result)->toContain('Error:');
});

it('describes itself with the namespace', function (): void {
    expect((string) (new KnowledgeSearch(namespace: 'legal'))->description())->toContain('[legal]');
});
