<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Http\Controllers;

use AndreAgroFerreira\AiSdkToolbox\Knowledge\Knowledge;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSource;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\KnowledgeSyncer;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeDocument;
use AndreAgroFerreira\AiSdkToolbox\Knowledge\SyncReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class KnowledgeController extends Controller
{
    public function documents(Request $request): JsonResponse
    {
        $documents = KnowledgeDocument::query()
            ->when(is_string($request->query('namespace')), fn ($query) => $query->where('namespace', $request->query('namespace')))
            ->when(is_string($request->query('status')), fn ($query) => $query->where('status', $request->query('status')))
            ->orderBy('namespace')
            ->orderBy('path')
            ->limit(500)
            ->get();

        return new JsonResponse([
            'data' => $documents->map(fn (KnowledgeDocument $document): array => [
                'id' => $document->id,
                'namespace' => $document->namespace,
                'source' => $document->source,
                'path' => $document->path,
                'status' => $document->status,
                'error' => $document->error,
                'chunk_count' => $document->chunk_count,
                'synced_at' => $document->synced_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function sync(Request $request, KnowledgeSyncer $syncer): JsonResponse
    {
        $name = $request->input('source');
        $inline = $request->boolean('inline');

        if (is_string($name)) {
            $source = $syncer->source($name);

            if (! $source instanceof KnowledgeSource) {
                return new JsonResponse(['message' => sprintf('Unknown knowledge source [%s].', $name)], 404);
            }

            $sources = [$source];
        } else {
            $sources = $syncer->sources();

            if ($sources === []) {
                return new JsonResponse(['message' => 'No knowledge sources configured.'], 422);
            }
        }

        return new JsonResponse([
            'data' => array_map(
                fn (KnowledgeSource $source): array => $this->serializeReport($syncer->sync($source, $inline)),
                $sources,
            ),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('query');
        $namespace = $request->query('namespace', 'default');

        if (! is_string($query) || mb_trim($query) === '') {
            return new JsonResponse(['message' => 'The [query] parameter is required.'], 422);
        }

        $limit = $request->integer('limit', 8);
        $limit = max(1, min(50, $limit));

        $results = Knowledge::namespace(is_string($namespace) ? $namespace : 'default')
            ->search($query, limit: $limit);

        return new JsonResponse([
            'data' => $results->map(fn ($chunk): array => [
                'content' => $chunk->content,
                'score' => $chunk->score,
                'path' => $chunk->path,
                'metadata' => $chunk->metadata,
            ])->all(),
        ]);
    }

    public function status(): JsonResponse
    {
        $documents = KnowledgeDocument::query()->get();

        return new JsonResponse([
            'data' => $documents->groupBy('namespace')->map(
                fn ($group, string $namespace): array => [
                    'namespace' => $namespace,
                    'documents' => $group->count(),
                    'synced' => $group->where('status', KnowledgeDocument::STATUS_SYNCED)->count(),
                    'errors' => $group->where('status', KnowledgeDocument::STATUS_ERROR)->count(),
                    'chunks' => \AndreAgroFerreira\AiSdkToolbox\Knowledge\Models\KnowledgeChunk::query()->where('namespace', $namespace)->count(),
                ],
            )->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReport(SyncReport $report): array
    {
        return [
            'source' => $report->source,
            'synced' => $report->synced,
            'skipped' => $report->skipped,
            'deleted' => $report->deleted,
            'failed' => $report->failed,
        ];
    }
}
