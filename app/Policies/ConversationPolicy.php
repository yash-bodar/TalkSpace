<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view the model.
     *
     * // YB - 24-08-2026 code comment
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user->id)
            || ($conversation->type === 'group' && $conversation->joinRequests()->where('user_id', $user->id)->where('status', 'pending')->exists());
    }

    /**
     * Determine whether the user can send messages in the conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user->id);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * // YB - 27-08-2026 code comment
     */
    public function delete(User $user, Conversation $conversation): bool
    {
        if ($conversation->type === 'group') {
            return $conversation->isAdmin($user->id);
        }

        return $conversation->isParticipant($user->id);
    }
}
