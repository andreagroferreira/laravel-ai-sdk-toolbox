<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts\ContentExtractor;

/**
 * PDF extractor backed by smalot/pdfparser when it is installed.
 * Register it through the ExtractorRegistry: it is only available
 * when the suggested package exists.
 */
final class PdfExtractor implements ContentExtractor
{
    public static function isAvailable(): bool
    {
        return class_exists(\Smalot\PdfParser\Parser::class);
    }

    public function supports(string $extension): bool
    {
        return mb_strtolower($extension) === 'pdf';
    }

    public function extract(string $contents): string
    {
        $parser = new \Smalot\PdfParser\Parser;

        return mb_trim($parser->parseContent($contents)->getText());
    }
}
