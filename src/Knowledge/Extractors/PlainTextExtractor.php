<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts\ContentExtractor;

final class PlainTextExtractor implements ContentExtractor
{
    public function supports(string $extension): bool
    {
        return in_array(mb_strtolower($extension), ['md', 'markdown', 'txt', 'text'], true);
    }

    public function extract(string $contents): string
    {
        return $contents;
    }
}
