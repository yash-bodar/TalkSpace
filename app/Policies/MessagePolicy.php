<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    /**
     * Determine whether the user can view the message.
     *
     * // YB - 24-08-2026 code comment
     */
    public function view(User $user, Message $message): bool
    {
        return $message->conversation->isParticipant($user->id);
    }

    /**
     * Determine whether the user can delete the message.
     *
     * // YB - 24-08-2026 code comment
     */
    public function delete(User $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->id;
    }
}
