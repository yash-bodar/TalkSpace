<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/**
 * Chat conversation authorization channel.
 *
 * // YB - 27-08-2026 code comment
 */
Broadcast::channel('chat.{conversationId}', function (User $user, int $conversationId) {
    return Conversation::where('id', $conversationId)
        ->whereHas('participants', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        })
        ->exists();
});

/**
 * Private user notifications channel.
 *
 * // YB - 24-08-2026 code comment
 */
Broadcast::channel('user.{userId}', function (User $user, int $userId) {
    return (int) $user->id === (int) $userId;
});

/**
 * Presence channel for tracking who is currently online.
 *
 * // YB - 24-08-2026 code comment
 */
Broadcast::channel('online', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar_url' => $user->avatar_url,
    ];
});

/**
 * Private authenticated channel for heartbeats.
 *
 * // YB - 27-08-2026 code comment
 */
Broadcast::channel('online-heartbeats', function (User $user) {
    return (bool) $user->id;
});
