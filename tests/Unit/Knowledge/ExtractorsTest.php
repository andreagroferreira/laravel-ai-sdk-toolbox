<?php

declare(strict_types=1);

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors\ExtractorRegistry;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors\HtmlExtractor;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors\PdfExtractor;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors\PlainTextExtractor;

it('extracts plain text as-is', function (): void {
    $extractor = new PlainTextExtractor;

    expect($extractor->supports('md'))->toBeTrue()
        ->and($extractor->supports('TXT'))->toBeTrue()
        ->and($extractor->supports('pdf'))->toBeFalse()
        ->and($extractor->extract("# Title\n\nBody"))->toBe("# Title\n\nBody");
});

it('strips html into plain text', function (): void {
    $extractor = new HtmlExtractor;

    $text = $extractor->extract('<h1>Refunds</h1><p>Allowed within <b>30 days</b> &amp; counting.</p>');

    expect($extractor->supports('html'))->toBeTrue()
        ->and($text)->toBe('Refunds Allowed within 30 days & counting.');
});

it('resolves extractors by extension through the registry', function (): void {
    $registry = new ExtractorRegistry;

    expect($registry->for('md'))->toBeInstanceOf(PlainTextExtractor::class)
        ->and($registry->for('html'))->toBeInstanceOf(HtmlExtractor::class)
        ->and($registry->for('bin'))->toBeNull();

    expect($registry->for('pdf') instanceof PdfExtractor)->toBe(PdfExtractor::isAvailable());
});
