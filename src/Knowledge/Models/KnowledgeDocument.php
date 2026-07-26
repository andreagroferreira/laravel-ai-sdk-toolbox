<?php

declare(strict_types=1);

namespace AndreAgroFerreira\AiSdkToolbox\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int $id
 * @property string $namespace
 * @property string $source
 * @property string $path
 * @property string $hash
 * @property string|null $mime
 * @property int $size
 * @property int $chunk_count
 * @property string $status
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $synced_at
 */
final class KnowledgeDocument extends Model
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SYNCED = 'synced';

    public const string STATUS_ERROR = 'error';

    protected $table = 'knowledge_documents';

    protected $guarded = [];

    /**
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
        ];
    }
}
