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
        Schema::table('conversation_users', function (Blueprint $table) {
            $table->timestamp('last_delivered_at')->nullable()->after('last_read_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 26-08-2026 code comment
     */
    public function down(): void
    {
        Schema::table('conversation_users', function (Blueprint $table) {
            $table->dropColumn('last_delivered_at');
        });
    }
};
