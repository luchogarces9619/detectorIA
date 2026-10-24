<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 500);
            $table->string('mime_type', 150);
            $table->string('extension', 10);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->unsignedInteger('page_count')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('paragraph_count')->default(0);
            $table->string('language', 8)->nullable();
            $table->enum('status', ['uploaded', 'validating', 'processing', 'ready', 'failed', 'deleted'])
                ->default('uploaded');
            $table->string('failure_code', 50)->nullable();
            $table->text('failure_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('uploaded_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('storage_path');
            $table->index(['user_id', 'created_at']);
            $table->index('status');
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
