<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('subscription_plan')->default('free')->after('role');
            $table->integer('upload_limit')->default(5)->after('subscription_plan');
            $table->integer('uploads_used')->default(0)->after('upload_limit');
            $table->timestamp('subscription_expires_at')->nullable()->after('uploads_used');
            $table->boolean('can_upload')->default(true)->after('subscription_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['subscription_plan', 'upload_limit', 'uploads_used', 'subscription_expires_at', 'can_upload']);
        });
    }
};
