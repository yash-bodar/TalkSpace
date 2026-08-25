<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * // YB - 25-08-2026 add delivered_at to messages table for double tick delivery status
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('read_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 25-08-2026 code comment
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('delivered_at');
        });
    }
};
