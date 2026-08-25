<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\ConversationUser;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * // YB - 24-08-2026 code comment
     */
    public function run(): void
    {
        $password = Hash::make('password');

        $user1 = User::firstOrCreate(
            ['email' => 'yash@talkspace.com'],
            ['name' => 'Yash Bodar', 'password' => $password]
        );

        $user2 = User::firstOrCreate(
            ['email' => 'alex@talkspace.com'],
            ['name' => 'Alex Rivera', 'password' => $password]
        );

        $user3 = User::firstOrCreate(
            ['email' => 'sarah@talkspace.com'],
            ['name' => 'Sarah Connor', 'password' => $password]
        );

        $user4 = User::firstOrCreate(
            ['email' => 'john@talkspace.com'],
            ['name' => 'John Doe', 'password' => $password]
        );

        // Create a sample direct conversation between Yash and Alex
        $directConv = Conversation::create([
            'type' => 'direct',
            'title' => null,
            'last_message_at' => now(),
        ]);

        ConversationUser::create([
            'conversation_id' => $directConv->id,
            'user_id' => $user1->id,
            'role' => 'member',
            'last_read_at' => now(),
        ]);

        ConversationUser::create([
            'conversation_id' => $directConv->id,
            'user_id' => $user2->id,
            'role' => 'member',
            'last_read_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $directConv->id,
            'sender_id' => $user2->id,
            'body' => 'Hey Yash! Welcome to TalkSpace with Laravel Reverb & Echo!',
            'type' => 'text',
            'created_at' => now()->subMinutes(5),
        ]);

        Message::create([
            'conversation_id' => $directConv->id,
            'sender_id' => $user1->id,
            'body' => 'Hey Alex! WebSockets and real-time broadcasts are up and running! 🚀',
            'type' => 'text',
            'created_at' => now()->subMinutes(2),
        ]);

        // Create a sample General Tech group
        $groupConv = Conversation::create([
            'type' => 'group',
            'title' => 'TalkSpace Core Team 🚀',
            'last_message_at' => now(),
        ]);

        ConversationUser::create([
            'conversation_id' => $groupConv->id,
            'user_id' => $user1->id,
            'role' => 'admin',
            'last_read_at' => now(),
        ]);

        ConversationUser::create([
            'conversation_id' => $groupConv->id,
            'user_id' => $user2->id,
            'role' => 'member',
            'last_read_at' => now(),
        ]);

        ConversationUser::create([
            'conversation_id' => $groupConv->id,
            'user_id' => $user3->id,
            'role' => 'member',
            'last_read_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $groupConv->id,
            'sender_id' => $user3->id,
            'body' => 'Hello everyone! Real-time group messaging is active.',
            'type' => 'text',
            'created_at' => now()->subMinute(),
        ]);
    }
}
