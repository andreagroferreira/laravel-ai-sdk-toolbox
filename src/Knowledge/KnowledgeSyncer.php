<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Jobs\SyncKnowledgeDocument;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class KnowledgeSyncer
{
    public function __construct(
        private readonly Repository $config,
        private readonly KnowledgePipeline $pipeline,
    ) {}

    /**
     * @return array<int, KnowledgeSource>
     */
    public function sources(): array
    {
        /** @var array<string, array<string, mixed>> $sources */
        $sources = $this->config->get('ai-sdk-toolbox.knowledge.sources', []);

        return array_map(
            fn (string $name, array $config): KnowledgeSource => KnowledgeSource::fromConfig($name, $config),
            array_keys($sources),
            $sources,
        );
    }

    public function source(string $name): ?KnowledgeSource
    {
        foreach ($this->sources() as $source) {
            if ($source->name === $name) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Synchronize one source: dispatch (or inline-run) jobs for new/changed
     * documents and delete documents that disappeared from the disk.
     */
    public function sync(KnowledgeSource $source, bool $inline): SyncReport
    {
        $synced = 0;
        $skipped = 0;
        $deleted = 0;
        $failed = [];

        $files = Storage::disk($source->disk)->allFiles($source->path);
        $files = array_values(array_filter(
            array_filter($files, is_string(...)),
            fn (string $file): bool => in_array(mb_strtolower(pathinfo($file, PATHINFO_EXTENSION)), $source->extensions, true)
                && $this->pipeline->supports(mb_strtolower(pathinfo($file, PATHINFO_EXTENSION))),
        ));

        $prefix = $source->path === '' ? '' : mb_rtrim($source->path, '/').'/';
        $seenPaths = [];

        foreach ($files as $file) {
            $relativePath = str_starts_with($file, $prefix) ? mb_substr($file, mb_strlen($prefix)) : $file;
            $seenPaths[] = $relativePath;

            $document = KnowledgeDocument::query()
                ->where('namespace', $source->namespace)
                ->where('source', $source->disk)
                ->where('path', $relativePath)
                ->first();

            try {
                $hash = $this->pipeline->hash($source->disk, $file);
            } catch (Throwable $throwable) {
                $failed[] = sprintf('%s (%s)', $relativePath, $throwable->getMessage());

                continue;
            }

            if ($document instanceof KnowledgeDocument && $document->hash === $hash && $document->status === KnowledgeDocument::STATUS_SYNCED) {
                $skipped++;

                continue;
            }

            $document ??= KnowledgeDocument::query()->create([
                'namespace' => $source->namespace,
                'source' => $source->disk,
                'path' => $relativePath,
                'hash' => $hash,
                'mime' => Storage::disk($source->disk)->mimeType($file) ?: null,
                'size' => Storage::disk($source->disk)->size($file),
                'status' => KnowledgeDocument::STATUS_PENDING,
            ]);

            if ($inline) {
                try {
                    $this->pipeline->syncDocument($document, $source);
                } catch (Throwable $throwable) {
                    $failed[] = sprintf('%s (%s)', $relativePath, $throwable->getMessage());

                    continue;
                }
            } else {
                SyncKnowledgeDocument::dispatch($document->id, $source)
                    ->onQueue($this->queue());
            }

            $synced++;
        }

        $deleted = $this->deleteMissing($source, $seenPaths);

        return new SyncReport($source->name, $synced, $skipped, $deleted, $failed);
    }

    /**
     * @param  array<int, string>  $seenPaths
     */
    private function deleteMissing(KnowledgeSource $source, array $seenPaths): int
    {
        $documents = KnowledgeDocument::query()
            ->where('namespace', $source->namespace)
            ->where('source', $source->disk)
            ->get();

        $deleted = 0;

        foreach ($documents as $document) {
            if (! in_array($document->path, $seenPaths, true)) {
                $this->pipeline->deleteDocument($document);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function queue(): string
    {
        $queue = $this->config->get('ai-sdk-toolbox.knowledge.sync.queue', 'default');

        return is_string($queue) ? $queue : 'default';
    }
}
