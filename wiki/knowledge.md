# Knowledge Base

A unified ingestion and retrieval pipeline: point at any Laravel filesystem disk (local, S3, FTP), and your agents can search your documents semantically — with incremental sync and isolated namespaces.

---

## The problem this solves

RAG with the AI SDK is three lines for a demo (`SimilaritySearch` + pgvector). In production it becomes: extract text from real documents, chunk them sanely, batch embeddings, track what's already synced, handle deletions, isolate tenants, and expose it as a clean agent tool. That's the work this module does once, so you never do it again.

## The pipeline

```
Source (any Flysystem disk + path)
   → ai:kb-sync (sha256 diff, deletions included)
   → extract (md / txt / html / pdf*)
   → chunk (markdown-aware or fixed-size, with overlap)
   → embed (AI SDK, batched, cached)
   → store (pgvector, native Laravel 13 vector column)
   → retrieve (KnowledgeSearch tool or Knowledge::namespace()->search())
```

\* PDF requires `composer require smalot/pdfparser` (suggested, auto-detected).

## Setup

**1. Install and migrate:**

```bash
composer require andreagroferreira/laravel-ai-sdk-toolbox
php artisan vendor:publish --tag=ai-sdk-toolbox-migrations
php artisan migrate
```

> **PostgreSQL required.** The pgvector driver uses Laravel 13's native vector column (HNSW index). The chunks table falls back to JSON on other databases for development, but semantic search needs pgvector.

**2. Configure sources** (`config/ai-sdk-toolbox.php`):

```php
'knowledge' => [
    'embeddings' => [
        'provider' => null,           // null = your default AI provider
        'model' => null,              // e.g. 'text-embedding-3-small'
        'dimensions' => 1536,
        'cache' => true,              // avoid re-embedding identical content
    ],
    'chunking' => ['size' => 1500, 'overlap' => 200],
    'sync' => ['queue' => 'default', 'batch' => 100],
    'sources' => [
        'docs' => [
            'disk' => 's3',
            'path' => 'kb/',
            'namespace' => 'docs',
            'chunker' => 'markdown',
            'extensions' => ['md', 'txt', 'html', 'pdf'],
        ],
        'legal' => [
            'disk' => 'local',
            'path' => 'legal',
            'namespace' => 'legal',
            'chunker' => 'plain',
            'extensions' => ['md', 'txt'],
        ],
    ],
],
```

Sources are any disk from your `config/filesystems.php` — `local`, `s3` (needs `league/flysystem-aws-s3-v3`), `ftp` (needs `league/flysystem-ftp`), or custom.

**3. Sync:**

```bash
php artisan ai:kb-sync            # all sources, queued jobs
php artisan ai:kb-sync docs       # one source
php artisan ai:kb-sync docs --sync  # inline, great for dev
php artisan ai:kb-status          # documents, chunks, errors per namespace
```

## How sync works

1. Scans the disk recursively under `path`, filtered by `extensions`
2. **Diffs by content hash** (sha256): unchanged documents are skipped
3. New/changed documents are extracted, chunked, embedded in batches, and **atomically replaced** in the store (readers never see a half-updated document)
4. Files that disappeared from the disk have their documents and chunks **deleted**
5. Outcomes are tracked per document (`pending` / `synced` / `error`) and emitted as events (`KnowledgeDocumentSynced`, `KnowledgeSyncFailed`)

Sync is idempotent — run it on a schedule:

```php
Schedule::command('ai:kb-sync')->hourly()->withoutOverlapping();
```

## Searching from agents

```php
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Tools\KnowledgeSearch;

final class SupportAgent implements Agent, HasTools
{
    use Promptable;

    public function tools(): iterable
    {
        return [
            new KnowledgeSearch(
                namespace: 'docs',
                limit: 8,
                minSimilarity: 0.4,
                rerank: true,               // optional, needs a reranking provider key
                description: 'Search the product documentation.',
            ),
        ];
    }
}
```

The tool returns matching chunks with path and score:

```
---
[path: kb/refunds.md | score: 0.91]
Refunds are allowed within 30 days of purchase...
---
[path: kb/digital-goods.md | score: 0.84]
Digital goods are not refundable after download...
```

> **Reranking** (`rerank: true`) over-fetches 3× and reorders with the SDK's `Reranking` (Cohere, Jina or VoyageAI). Without a configured reranking provider it degrades silently to plain vector search.

## Searching from PHP

```php
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Knowledge;

$results = Knowledge::namespace('legal')->search(
    'what is the refund policy for digital goods?',
    limit: 5,
    minSimilarity: 0.5,
);

foreach ($results as $chunk) {
    // $chunk->content, $chunk->score, $chunk->path, $chunk->metadata, $chunk->documentId
}
```

## Namespaces: isolation by design

Every document and chunk lives in a **namespace**. Searches never cross namespaces — use them for tenants, products, teams, or access levels:

```
'acme' => ['disk' => 'tenant-acme', 'path' => '', 'namespace' => 'acme', ...],
'globex' => ['disk' => 'tenant-globex', 'path' => '', 'namespace' => 'globex', ...],
```

## Chunking strategies

| Strategy | Behavior | Use for |
|---|---|---|
| `markdown` | Splits at heading boundaries, keeps the heading in each chunk's metadata, then size-bounds with overlap | Docs, guides, playbooks |
| `plain` | Fixed-size chunks with overlap | Notes, transcripts, raw text |

Defaults: `size: 1500` chars, `overlap: 200`. Tune via `knowledge.chunking`.

## Extending

**Custom extractors** — implement `ContentExtractor` and register:

```php
app(ExtractorRegistry::class)->register(new DocxExtractor);
```

**Custom vector stores** — the `VectorStore` contract (upsert / deleteDocument / search / purge) is the seam for Qdrant, Meilisearch or provider-hosted stores:

```php
$this->app->singleton(VectorStore::class, fn () => new QdrantStore(...));
```

## Events

```php
use AndreAgroFerreira\AiSdkToolbox\Events\KnowledgeDocumentSynced;
use AndreAgroFerreira\AiSdkToolbox\Events\KnowledgeSyncFailed;

Event::listen(KnowledgeSyncFailed::class, function (KnowledgeSyncFailed $event) {
    Log::warning('KB sync failed', [
        'path' => $event->document->path,
        'error' => $event->exception->getMessage(),
    ]);
});
```

## Production checklist

1. PostgreSQL with pgvector (Laravel creates the extension and HNSW index for you)
2. `embeddings.dimensions` must match your embedding model (1536 for `text-embedding-3-small`)
3. Queue worker running for async syncs (or use `--sync` for small sets)
4. Scheduled `ai:kb-sync` with `withoutOverlapping()`
5. Monitor `ai:kb-status` for documents in `error`
