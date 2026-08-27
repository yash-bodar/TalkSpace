<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * // YB - 26-08-2026 code comment
     */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->string('avatar_path')->nullable()->after('description');
            $table->boolean('is_public')->default(false)->after('avatar_path');
            $table->string('invite_code', 32)->nullable()->unique()->after('is_public');
        });
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 26-08-2026 code comment
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['description', 'avatar_path', 'is_public', 'invite_code']);
        });
    }
};
