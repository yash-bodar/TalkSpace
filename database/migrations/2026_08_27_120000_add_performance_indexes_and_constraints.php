<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * // YB - 27-08-2026 code comment
     */
    public function up(): void
    {
        // Ensure performance indexes exist
        if (! $this->hasIndex('messages', 'messages_sender_id_index')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->index('sender_id', 'messages_sender_id_index');
            });
        }

        if (! $this->hasIndex('conversation_users', 'conversation_users_user_id_index')) {
            Schema::table('conversation_users', function (Blueprint $table) {
                $table->index('user_id', 'conversation_users_user_id_index');
            });
        }

        if (! $this->hasIndex('conversation_users', 'conversation_users_user_id_last_delivered_at_index')) {
            Schema::table('conversation_users', function (Blueprint $table) {
                $table->index(['user_id', 'last_delivered_at'], 'conversation_users_user_id_last_delivered_at_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 27-08-2026 code comment
     */
    public function down(): void
    {
        if ($this->hasIndex('conversation_users', 'conversation_users_user_id_last_delivered_at_index')) {
            Schema::table('conversation_users', function (Blueprint $table) {
                $table->dropIndex('conversation_users_user_id_last_delivered_at_index');
            });
        }

        if ($this->hasIndex('conversation_users', 'conversation_users_user_id_index')) {
            Schema::table('conversation_users', function (Blueprint $table) {
                $table->dropIndex('conversation_users_user_id_index');
            });
        }

        if ($this->hasIndex('messages', 'messages_sender_id_index')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropIndex('messages_sender_id_index');
            });
        }
    }

    /**
     * Helper to safely check if index exists.
     *
     * // YB - 27-08-2026 code comment
     */
    private function hasIndex(string $table, string $name): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$name]);
        return count($indexes) > 0;
    }
};
