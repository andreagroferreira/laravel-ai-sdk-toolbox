<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Events;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

final readonly class KnowledgeSyncFailed
{
    use Dispatchable;

    public function __construct(
        public KnowledgeDocument $document,
        public Throwable $exception,
    ) {}
}
