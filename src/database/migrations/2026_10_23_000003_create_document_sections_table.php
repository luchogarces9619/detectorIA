<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained()->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->unsignedInteger('position');
            $table->unsignedSmallInteger('level')->default(1);
            $table->enum('type', [
                'title', 'heading', 'abstract', 'introduction', 'methods', 'results',
                'discussion', 'conclusion', 'references', 'appendix', 'other',
            ])->default('other');
            $table->string('title', 500)->nullable();
            $table->unsignedInteger('start_page')->nullable();
            $table->unsignedInteger('end_page')->nullable();
            $table->unsignedBigInteger('char_start')->default(0);
            $table->unsignedBigInteger('char_end')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'position']);
            $table->index(['document_id', 'type']);
        });

        // PostgreSQL exige que la clave primaria exista antes de crear una
        // autorreferencia. Laravel añade la PK al final del CREATE TABLE, así
        // que la FK se crea en una sentencia posterior.
        Schema::table('document_sections', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('document_sections')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sections');
    }
};
