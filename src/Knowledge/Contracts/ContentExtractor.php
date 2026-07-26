<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts;

interface ContentExtractor
{
    /**
     * Whether this extractor supports the given file extension.
     */
    public function supports(string $extension): bool;

    /**
     * Extract plain text from raw file contents.
     */
    public function extract(string $contents): string;
}
