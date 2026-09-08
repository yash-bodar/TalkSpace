<?php

namespace App\Services;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\ConversationUser;
use App\Models\Message;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChatService
{
    /**
     * Retrieve all conversations for the authenticated user ordered by last activity.
     *
     * // YB - 24-08-2026 code comment
     */
    public function getUserConversations(User $user): Collection
    {
        return $user->conversations()
            ->with([
                'participants',
                'conversationUsers',
                'latestMessage.sender',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Retrieve messages for a given conversation with sender loaded.
     *
     * // YB - 24-08-2026 code comment
     */
    public function getConversationMessages(Conversation $conversation, int $limit = 100): Collection
    {
        return $conversation->messages()
            ->with('sender')
            ->latest('id')
            ->take($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Find existing direct 1-1 conversation between two users or create a new one atomically.
     *
     * // YB - 24-08-2026 code comment
     */
    public function getOrCreateDirectConversation(User $user, int $recipientId): Conversation
    {
        if ($user->id === $recipientId) {
            throw new \InvalidArgumentException('Cannot create a conversation with yourself.');
        }

        // Look for existing direct conversation between these two users
        $existingConversation = Conversation::where('type', 'direct')
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->whereHas('participants', function ($query) use ($recipientId) {
                $query->where('users.id', $recipientId);
            })
            ->first();

        if ($existingConversation) {
            return $existingConversation->loadMissing(['participants', 'conversationUsers', 'latestMessage.sender']);
        }

        return DB::transaction(function () use ($user, $recipientId) {
            $conversation = Conversation::create([
                'type' => 'direct',
                'title' => null,
                'last_message_at' => now(),
            ]);

            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => 'member',
                'last_read_at' => now(),
            ]);

            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $recipientId,
                'role' => 'member',
                'last_read_at' => null,
            ]);

            Log::info('Direct conversation created', [
                'conversation_id' => $conversation->id,
                'user_1' => $user->id,
                'user_2' => $recipientId,
            ]);

            return $conversation->load(['participants', 'conversationUsers', 'latestMessage.sender']);
        });
    }

    /**
     * Create a group conversation with multiple participants.
     *
     * // YB - 24-08-2026 code comment
     */
    public function createGroupConversation(User $creator, string $title, array $participantIds): Conversation
    {
        return DB::transaction(function () use ($creator, $title, $participantIds) {
            $conversation = Conversation::create([
                'type' => 'group',
                'title' => $title,
                'last_message_at' => now(),
            ]);

            // Add creator as admin
            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $creator->id,
                'role' => 'admin',
                'last_read_at' => now(),
            ]);

            // Add all other members
            $uniqueParticipantIds = array_unique(array_diff($participantIds, [$creator->id]));
            foreach ($uniqueParticipantIds as $participantId) {
                ConversationUser::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $participantId,
                    'role' => 'member',
                    'last_read_at' => null,
                ]);
            }

            Log::info('Group conversation created', [
                'conversation_id' => $conversation->id,
                'creator_id' => $creator->id,
                'participants_count' => count($uniqueParticipantIds) + 1,
            ]);

            return $conversation->load(['participants', 'conversationUsers', 'latestMessage.sender']);
        });
    }

    /**
     * Store and broadcast a new message.
     *
     * // YB - 24-08-2026 code comment
     */
    public function sendMessage(
        User $sender,
        Conversation $conversation,
        array $data,
        ?UploadedFile $attachment = null
    ): Message {
        if (! $conversation->isParticipant($sender->id)) {
            throw new AccessDeniedHttpException('User is not a participant in this conversation.');
        }

        return DB::transaction(function () use ($sender, $conversation, $data, $attachment) {
            $attachmentPath = null;
            $attachmentName = null;
            $attachmentSize = null;
            $type = 'text';

            if ($attachment) {
                $attachmentPath = $attachment->store('attachments/' . $conversation->id, 'public');
                $attachmentName = $attachment->getClientOriginalName();
                $attachmentSize = $attachment->getSize();
                $mime = $attachment->getMimeType();

                $type = str_starts_with($mime, 'image/') ? 'image' : 'file';
            }

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $data['body'] ?? null,
                'type' => $type,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_size' => $attachmentSize,
            ]);

            // Update conversation last_message_at timestamp
            $conversation->update(['last_message_at' => $message->created_at]);

            // Mark message as read for the sender
            ConversationUser::where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update(['last_read_at' => now()]);

            $message->load(['sender', 'conversation.participants']);

            // Broadcast real-time event
            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Broadcast MessageSent failed: ' . $e->getMessage());
            }

            return $message;
        });
    }

    /**
     * Mark all unread messages as read for a user in a conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function markConversationAsRead(User $user, Conversation $conversation): void
    {
        $now = now();

        ConversationUser::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => $now]);

        // Also update message read_at timestamp in database
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now, 'delivered_at' => $now]);

        try {
            broadcast(new MessageRead($conversation->id, $user, $now))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast MessageRead failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark all delivered messages when notification or broadcast is received by recipient.
     *
     * // YB - 25-08-2026 code comment
     */
    public function markConversationAsDelivered(User $user, Conversation $conversation): void
    {
        $now = now();

        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => $now]);

        try {
            broadcast(new \App\Events\MessageDelivered($conversation->id, $user, $now))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast MessageDelivered failed: ' . $e->getMessage());
        }
    }

    /**
     * Edit an existing message.
     *
     * // YB - 25-08-2026 code comment
     */
    public function editMessage(User $user, Message $message, string $newBody): Message
    {
        if ($message->sender_id !== $user->id) {
            throw new AccessDeniedHttpException('You can only edit your own messages.');
        }

        if ($message->is_deleted_for_everyone) {
            throw new \InvalidArgumentException('Cannot edit a deleted message.');
        }

        $message->update([
            'body' => $newBody,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        $message->load(['sender', 'conversation']);

        try {
            broadcast(new \App\Events\MessageUpdated($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast MessageUpdated edit failed: ' . $e->getMessage());
        }

        return $message;
    }

    /**
     * Delete message for everyone (WhatsApp style).
     *
     * // YB - 25-08-2026 code comment
     */
    public function deleteMessageForEveryone(User $user, Message $message): Message
    {
        if ($message->sender_id !== $user->id) {
            throw new AccessDeniedHttpException('You can only delete your own messages for everyone.');
        }

        $message->update([
            'body' => '🚫 This message was deleted',
            'is_deleted_for_everyone' => true,
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_size' => null,
        ]);

        $message->load(['sender', 'conversation']);

        try {
            broadcast(new \App\Events\MessageUpdated($message))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast MessageUpdated delete for everyone failed: ' . $e->getMessage());
        }

        return $message;
    }

    /**
     * Delete message for current user only.
     *
     * // YB - 25-08-2026 code comment
     */
    public function deleteMessageForMe(User $user, Message $message): Message
    {
        $deletedFor = $message->deleted_for_user_ids ?? [];
        if (! in_array($user->id, $deletedFor)) {
            $deletedFor[] = $user->id;
            $message->update([
                'deleted_for_user_ids' => $deletedFor,
            ]);
        }

        return $message;
    }

    /**
     * Broadcast WebRTC call signal.
     *
     * // YB - 25-08-2026 code comment
     */
    public function signalCall(User $user, Conversation $conversation, array $data): void
    {
        if (! $conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('User is not a participant in this conversation.');
        }

        try {
            broadcast(new \App\Events\CallSignaled(
                $conversation->id,
                $user,
                $data['type'],
                $data['call_type'] ?? 'audio',
                $data['payload'] ?? null
            ))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast CallSignaled failed: ' . $e->getMessage());
        }
    }

    /**
     * Log a call status message into conversation history.
     *
     * // YB - 25-08-2026 code comment
     */
    public function logCallMessage(User $user, Conversation $conversation, string $status, string $callType, ?string $duration = null): Message
    {
        if (! $conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('User is not a participant in this conversation.');
        }

        return DB::transaction(function () use ($user, $conversation, $status, $callType, $duration) {
            $callLabel = $callType === 'video' ? 'Video call' : 'Voice call';
            
            if ($status === 'missed') {
                $body = "Missed {$callLabel}";
            } elseif ($status === 'completed' && $duration) {
                $body = "{$callLabel} • {$duration}";
            } else {
                $body = "{$callLabel}";
            }

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $body,
                'type' => 'system',
                'attachment_name' => $status, // 'missed', 'completed', 'declined'
                'attachment_path' => $callType, // 'audio', 'video'
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);
            $message->load(['sender', 'conversation.participants']);

            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Broadcast call MessageSent failed: ' . $e->getMessage());
            }

            return $message;
        });
    }

    /**
     * Broadcast user typing indicator state.
     *
     * // YB - 24-08-2026 code comment
     */
    public function broadcastTyping(User $user, Conversation $conversation, bool $isTyping): void
    {
        if ($conversation->isParticipant($user->id)) {
            try {
                broadcast(new UserTyping($conversation->id, $user, $isTyping))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Broadcast UserTyping failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Search users to start new chats with.
     *
     * // YB - 24-08-2026 code comment
     */
    public function searchUsers(User $currentUser, ?string $query = null, int $limit = 20): Collection
    {
        return User::where('id', '!=', $currentUser->id)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                });
            })
            ->orderBy('name')
            ->take($limit)
            ->get();
    }
}
