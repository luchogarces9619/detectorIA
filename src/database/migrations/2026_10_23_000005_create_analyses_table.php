<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('model_version_id')->nullable()
                ->constrained('model_versions')->restrictOnDelete();
            $table->string('engine_version', 50);
            $table->enum('status', ['queued', 'running', 'completed', 'failed', 'cancelled'])
                ->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->char('document_sha256', 64);
            $table->jsonb('parameters')->nullable();
            $table->jsonb('summary')->nullable();
            $table->decimal('aggregate_score', 6, 5)->nullable();
            $table->enum('confidence', ['insufficient', 'low', 'medium', 'high'])
                ->default('insufficient');
            $table->enum('conclusion', ['not_conclusive', 'no_signals', 'review_suggested', 'mixed_signals'])
                ->default('not_conclusive');
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index('model_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
