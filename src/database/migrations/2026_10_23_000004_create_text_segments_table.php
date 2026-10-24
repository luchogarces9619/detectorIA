<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('text_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->nullable()
                ->constrained('document_sections')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->enum('kind', [
                'paragraph', 'heading', 'caption', 'table', 'reference', 'list_item', 'other',
            ])->default('paragraph');
            $table->longText('text');
            $table->char('content_hash', 64);
            $table->unsignedBigInteger('char_start')->default(0);
            $table->unsignedBigInteger('char_end')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('page_start')->nullable();
            $table->unsignedInteger('page_end')->nullable();
            $table->boolean('is_excluded')->default(false);
            $table->string('exclusion_reason', 100)->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'position']);
            $table->index(['document_id', 'is_excluded']);
            $table->index('section_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('text_segments');
    }
};
