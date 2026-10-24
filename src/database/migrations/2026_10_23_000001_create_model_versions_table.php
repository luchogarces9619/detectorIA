<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('version', 50);
            $table->string('provider', 150)->nullable();
            $table->string('task', 50)->default('text-classification');
            $table->enum('status', ['draft', 'candidate', 'active', 'retired'])->default('draft');
            $table->string('artifact_path', 500)->nullable();
            $table->char('artifact_sha256', 64)->nullable();
            $table->jsonb('config')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->timestampTz('trained_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index('status');
            $table->index('activated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_versions');
    }
};
