<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * // YB - 25-08-2026 add edit, delete for everyone, and delete for me fields
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_edited')->default(false)->after('read_at');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            $table->boolean('is_deleted_for_everyone')->default(false)->after('edited_at');
            $table->json('deleted_for_user_ids')->nullable()->after('is_deleted_for_everyone');
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
            $table->dropColumn([
                'is_edited',
                'edited_at',
                'is_deleted_for_everyone',
                'deleted_for_user_ids',
            ]);
        });
    }
};
