<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge;

use AndreAgroFerreira\AiSdkToolbox\Events\KnowledgeDocumentSynced;
use AndreAgroFerreira\AiSdkToolbox\Events\KnowledgeSyncFailed;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunkers\MarkdownChunker;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Chunkers\PlainTextChunker;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Contracts\Chunker;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Extractors\ExtractorRegistry;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Embeddings;
use RuntimeException;
use Throwable;

final class KnowledgePipeline
{
    public function __construct(
        private readonly Repository $config,
        private readonly ExtractorRegistry $extractors,
        private readonly VectorStore $store,
    ) {}

    /**
     * Extract, chunk, embed and store a document. Updates the document
     * record and dispatches the outcome events.
     */
    public function syncDocument(KnowledgeDocument $document, KnowledgeSource $source): void
    {
        try {
            $fullPath = ($source->path === '' ? '' : mb_rtrim($source->path, '/').'/').$document->path;
            $contents = Storage::disk($source->disk)->get($fullPath);

            if (! is_string($contents)) {
                throw new RuntimeException(sprintf('Could not read [%s] from disk [%s].', $document->path, $source->disk));
            }

            $text = $this->extract($document->path, $contents);
            $chunks = $this->chunker($source)->chunk($text, ['path' => $document->path, 'source' => $source->disk]);
            $embedded = $this->embed($chunks);

            $this->store->upsert($document->namespace, $document->id, $embedded);

            $document->update([
                'hash' => hash('sha256', $contents),
                'size' => mb_strlen($contents),
                'chunk_count' => count($embedded),
                'status' => KnowledgeDocument::STATUS_SYNCED,
                'error' => null,
                'synced_at' => now(),
            ]);

            KnowledgeDocumentSynced::dispatch($document);
        } catch (Throwable $throwable) {
            $document->update(['status' => KnowledgeDocument::STATUS_ERROR, 'error' => $throwable->getMessage()]);

            KnowledgeSyncFailed::dispatch($document, $throwable);

            throw $throwable;
        }
    }

    public function hash(string $disk, string $path): string
    {
        $contents = Storage::disk($disk)->get($path);

        if (! is_string($contents)) {
            throw new RuntimeException(sprintf('Could not read [%s] from disk [%s].', $path, $disk));
        }

        return hash('sha256', $contents);
    }

    /**
     * Delete a document and all of its chunks from the vector store.
     */
    public function deleteDocument(KnowledgeDocument $document): void
    {
        $this->store->deleteDocument($document->id);

        $document->delete();
    }

    public function supports(string $extension): bool
    {
        return $this->extractors->for($extension) !== null;
    }

    private function extract(string $path, string $contents): string
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $extractor = $this->extractors->for($extension);

        if ($extractor === null) {
            throw new RuntimeException(sprintf('No extractor supports the [%s] extension.', $extension));
        }

        return $extractor->extract($contents);
    }

    private function chunker(KnowledgeSource $source): Chunker
    {
        /** @var int $size */
        $size = $this->config->get('ai-sdk-toolbox.knowledge.chunking.size', 1500);
        /** @var int $overlap */
        $overlap = $this->config->get('ai-sdk-toolbox.knowledge.chunking.overlap', 200);

        return $source->chunker === 'markdown'
            ? new MarkdownChunker($size, $overlap)
            : new PlainTextChunker($size, $overlap);
    }

    /**
     * @param  array<int, Chunk>  $chunks
     * @return array<int, EmbeddedChunk>
     */
    private function embed(array $chunks): array
    {
        if ($chunks === []) {
            return [];
        }

        /** @var array<string, mixed> $config */
        $config = $this->config->get('ai-sdk-toolbox.knowledge.embeddings', []);
        /** @var int $batch */
        $batch = $this->config->get('ai-sdk-toolbox.knowledge.sync.batch', 100);
        $embedded = [];

        foreach (array_chunk($chunks, max(1, $batch)) as $group) {
            $builder = Embeddings::for(array_map(fn (Chunk $chunk): string => $chunk->content, $group));

            if (is_int($config['dimensions'] ?? null)) {
                $builder = $builder->dimensions($config['dimensions']);
            }

            if ($config['cache'] ?? true) {
                $builder = $builder->cache();
            }

            $response = $builder->generate(
                is_string($config['provider'] ?? null) ? $config['provider'] : null,
                is_string($config['model'] ?? null) ? $config['model'] : null,
            );

            foreach ($group as $i => $chunk) {
                $embedded[] = new EmbeddedChunk($chunk->content, $chunk->index, array_values($response->embeddings[$i]), $chunk->metadata);
            }
        }

        return $embedded;
    }
}
