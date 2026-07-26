<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dimensions = config('ai-sdk-toolbox.knowledge.embeddings.dimensions', 1536);
        $dimensions = is_int($dimensions) ? $dimensions : 1536;

        if (config('database.default') === 'pgsql') {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('knowledge_chunks', function (Blueprint $table) use ($dimensions): void {
            $table->id();
            $table->string('namespace')->index();
            $table->unsignedBigInteger('document_id')->index();
            $table->unsignedInteger('chunk_index');
            $table->text('content');

            if (config('database.default') === 'pgsql') {
                $table->vector('embedding', $dimensions)->index();
            } else {
                $table->json('embedding');
            }

            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
