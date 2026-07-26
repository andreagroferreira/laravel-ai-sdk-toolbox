<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunkers\MarkdownChunker;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunkers\PlainTextChunker;

it('splits plain text into sized chunks with overlap', function (): void {
    $content = str_repeat('abcdefghij', 100); // 1000 chars

    $chunks = (new PlainTextChunker(size: 300, overlap: 60))->chunk($content);

    expect(count($chunks))->toBeGreaterThanOrEqual(4)
        ->and($chunks[0]->index)->toBe(0)
        ->and(mb_strlen($chunks[0]->content))->toBeLessThanOrEqual(300)
        ->and($chunks[0]->content)->toStartWith('abcdefghij')
        ->and(mb_strlen($chunks[1]->content))->toBeLessThanOrEqual(300);
});

it('returns no chunks for empty content', function (): void {
    expect((new PlainTextChunker)->chunk(''))->toBe([])
        ->and((new PlainTextChunker)->chunk("  \n "))->toBe([]);
});

it('keeps metadata on every chunk', function (): void {
    $chunks = (new PlainTextChunker(size: 10, overlap: 0))->chunk('abcdefghijklmno', ['path' => 'a.md']);

    expect($chunks[0]->metadata['path'])->toBe('a.md');
});

it('splits markdown at headings and records them', function (): void {
    $markdown = <<<'MD'
# Refund Policy

Refunds are allowed within 30 days of purchase.

## Digital Goods

Digital goods are not refundable after download.

## Physical Goods

Physical goods require return shipment first.
MD;

    $chunks = (new MarkdownChunker(size: 1500, overlap: 0))->chunk($markdown);

    expect(count($chunks))->toBe(3)
        ->and($chunks[0]->metadata['heading'])->toBe('Refund Policy')
        ->and($chunks[1]->metadata['heading'])->toBe('Digital Goods')
        ->and($chunks[2]->metadata['heading'])->toBe('Physical Goods');
});

it('applies the size strategy to oversized markdown sections', function (): void {
    $markdown = '# Big'."\n\n".str_repeat('lorem ipsum ', 200);

    $chunks = (new MarkdownChunker(size: 200, overlap: 40))->chunk($markdown);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($chunk->metadata['heading'])->toBe('Big');
    }
});

it('handles markdown without headings', function (): void {
    $chunks = (new MarkdownChunker(size: 1500, overlap: 0))->chunk('Just a paragraph.');

    expect(count($chunks))->toBe(1)
        ->and($chunks[0]->metadata['heading'])->toBeNull();
});
