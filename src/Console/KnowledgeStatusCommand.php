<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Console;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeChunk;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Show the status of the knowledge base')]
#[Signature('ai:kb-status {namespace? : Filter by namespace}')]
final class KnowledgeStatusCommand extends Command
{
    public function handle(): int
    {
        $namespace = $this->argument('namespace');

        $documents = KnowledgeDocument::query()
            ->when(is_string($namespace), fn ($query) => $query->where('namespace', $namespace))
            ->orderBy('namespace')
            ->orderBy('path')
            ->get();

        if ($documents->isEmpty()) {
            $this->components->info('No knowledge documents yet. Configure knowledge.sources and run ai:kb-sync.');

            return self::SUCCESS;
        }

        $this->table(
            ['Namespace', 'Source', 'Path', 'Status', 'Chunks', 'Synced at'],
            $documents->map(fn (KnowledgeDocument $document): array => [
                $document->namespace,
                $document->source,
                $document->path,
                $document->status.($document->error !== null ? sprintf(' (%s)', mb_strimwidth($document->error, 0, 60, '…')) : ''),
                (string) $document->chunk_count,
                $document->synced_at?->toDateTimeString() ?? 'never',
            ])->all(),
        );

        foreach ($documents->groupBy('namespace') as $group => $groupDocuments) {
            $chunks = KnowledgeChunk::query()->where('namespace', $group)->count();
            $errors = $groupDocuments->where('status', KnowledgeDocument::STATUS_ERROR)->count();

            $this->components->twoColumnDetail(
                sprintf('[%s]', $group),
                sprintf('%d documents, %d chunks%s', $groupDocuments->count(), $chunks, $errors > 0 ? sprintf(', %d errors', $errors) : ''),
            );
        }

        return self::SUCCESS;
    }
}
