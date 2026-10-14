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
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_text', 255)->nullable()->after('last_active_at');
            $table->string('status_emoji', 32)->nullable()->after('status_text');
            $table->timestamp('status_expires_at')->nullable()->after('status_emoji');
            $table->json('web_push_subscription')->nullable()->after('status_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 14-09-2026 code comment
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status_text',
                'status_emoji',
                'status_expires_at',
                'web_push_subscription',
            ]);
        });
    }
};
