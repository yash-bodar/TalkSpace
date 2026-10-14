<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * // YB - 14-09-2026 code comment
     */
    public function up(): void
    {
        Schema::table('conversation_users', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('last_delivered_at');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');
            $table->boolean('is_archived')->default(false)->after('pinned_at');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->boolean('is_muted')->default(false)->after('archived_at');
            $table->timestamp('muted_until')->nullable()->after('is_muted');

            $table->index(['user_id', 'is_pinned'], 'idx_cu_user_pinned');
            $table->index(['user_id', 'is_archived'], 'idx_cu_user_archived');
        });
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 14-09-2026 code comment
     */
    public function down(): void
    {
        Schema::table('conversation_users', function (Blueprint $table) {
            $table->dropIndex('idx_cu_user_pinned');
            $table->dropIndex('idx_cu_user_archived');
            $table->dropColumn([
                'is_pinned',
                'pinned_at',
                'is_archived',
                'archived_at',
                'is_muted',
                'muted_until',
            ]);
        });
    }
};
