<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_predictions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('segment_id')->constrained('text_segments')->cascadeOnDelete();
            $table->foreignUuid('model_version_id')->nullable()
                ->constrained('model_versions')->nullOnDelete();
            $table->enum('label', ['human', 'ai', 'mixed', 'uncertain'])->default('uncertain');
            $table->decimal('score', 6, 5)->nullable();
            $table->enum('confidence', ['insufficient', 'low', 'medium', 'high'])
                ->default('insufficient');
            $table->jsonb('indicators')->nullable();
            $table->text('explanation')->nullable();
            $table->boolean('requires_review')->default(false);
            $table->timestamps();

            $table->unique(['analysis_id', 'segment_id']);
            $table->index(['analysis_id', 'requires_review']);
            $table->index('segment_id');
            $table->index('model_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('segment_predictions');
    }
};
