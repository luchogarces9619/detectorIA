<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('format', ['pdf'])->default('pdf');
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 500);
            $table->char('sha256', 64);
            $table->timestampTz('generated_at');
            $table->timestampTz('expires_at')->nullable();
            $table->timestamps();

            $table->unique('storage_path');
            $table->index(['analysis_id', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
