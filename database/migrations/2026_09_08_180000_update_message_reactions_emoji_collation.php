<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * // YB - 08-09-2026 code comment
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE message_reactions MODIFY emoji VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 08-09-2026 code comment
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE message_reactions MODIFY emoji VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
    }
};
