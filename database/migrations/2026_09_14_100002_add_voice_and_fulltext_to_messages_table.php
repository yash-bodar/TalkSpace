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
     * // YB - 14-09-2026 code comment
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->json('audio_waveform')->nullable()->after('attachment_size');
            $table->unsignedSmallInteger('audio_duration')->nullable()->after('audio_waveform');
            $table->json('mentioned_user_ids')->nullable()->after('audio_duration');
        });

        // Add MySQL FULLTEXT index on body column if using MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages ADD FULLTEXT idx_messages_body_fulltext(body)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * // YB - 14-09-2026 code comment
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE messages DROP INDEX idx_messages_body_fulltext');
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'audio_waveform',
                'audio_duration',
                'mentioned_user_ids',
            ]);
        });
    }
};
