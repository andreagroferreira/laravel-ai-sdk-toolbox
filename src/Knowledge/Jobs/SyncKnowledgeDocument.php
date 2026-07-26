<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Jobs;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgePipeline;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSource;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

final class SyncKnowledgeDocument implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        public readonly int $documentId,
        public readonly KnowledgeSource $source,
    ) {}

    public function handle(KnowledgePipeline $pipeline): void
    {
        $document = KnowledgeDocument::query()->find($this->documentId);

        if ($document instanceof KnowledgeDocument) {
            $pipeline->syncDocument($document, $this->source);
        }
    }

    public function failed(?Throwable $exception): void
    {
        KnowledgeDocument::query()->find($this->documentId)?->update([
            'status' => KnowledgeDocument::STATUS_ERROR,
            'error' => $exception?->getMessage(),
        ]);
    }
}
