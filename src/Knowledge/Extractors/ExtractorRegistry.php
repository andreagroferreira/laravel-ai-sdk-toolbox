<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts\ContentExtractor;

final class ExtractorRegistry
{
    /**
     * @var array<int, ContentExtractor>
     */
    private array $extractors;

    public function __construct()
    {
        $this->extractors = [
            new PlainTextExtractor,
            new HtmlExtractor,
        ];

        if (PdfExtractor::isAvailable()) {
            $this->extractors[] = new PdfExtractor;
        }
    }

    public function register(ContentExtractor $extractor): void
    {
        $this->extractors[] = $extractor;
    }

    public function for(string $extension): ?ContentExtractor
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($extension)) {
                return $extractor;
            }
        }

        return null;
    }
}
