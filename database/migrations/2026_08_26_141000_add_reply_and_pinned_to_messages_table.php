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
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('reply_to_id')->nullable()->after('conversation_id')->constrained('messages')->nullOnDelete();
            $table->boolean('is_pinned')->default(false)->after('is_deleted_for_everyone');
            $table->timestamp('pinned_at')->nullable()->after('is_pinned');

            $table->index(['conversation_id', 'is_pinned']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 26-08-2026 code comment
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropIndex(['conversation_id', 'is_pinned']);
            $table->dropColumn(['reply_to_id', 'is_pinned', 'pinned_at']);
        });
    }
};
