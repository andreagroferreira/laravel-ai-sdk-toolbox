<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read int $id
 * @property string $namespace
 * @property int $document_id
 * @property int $chunk_index
 * @property string $content
 * @property array<int, float> $embedding
 * @property array<string, mixed>|null $metadata
 */
final class KnowledgeChunk extends Model
{
    protected $table = 'knowledge_chunks';

    protected $guarded = [];

    /**
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }
}
