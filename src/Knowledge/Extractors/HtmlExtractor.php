<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts\ContentExtractor;

final class HtmlExtractor implements ContentExtractor
{
    public function supports(string $extension): bool
    {
        return in_array(mb_strtolower($extension), ['html', 'htm'], true);
    }

    public function extract(string $contents): string
    {
        $text = (string) preg_replace('/<[^>]+>/', ' ', $contents);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
