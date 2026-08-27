<?php

namespace App\Services;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\ConversationUser;
use App\Models\Message;
use App\Models\MessageReaction;
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
     * // YB - 27-08-2026 code comment
     */
    public function getUserConversations(User $user): Collection
    {
        return $user->conversations()
            ->with([
                'participants',
                'conversationUsers',
                'latestMessage.sender',
            ])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->id)
                    ->where(function ($sub) use ($user) {
                        $sub->whereNull('read_at')
                            ->orWhereRaw('messages.created_at > (
                                SELECT COALESCE(last_read_at, "1970-01-01 00:00:00")
                                FROM conversation_users
                                WHERE conversation_users.conversation_id = messages.conversation_id
                                AND conversation_users.user_id = ?
                            )', [$user->id]);
                    });
            }])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Retrieve latest messages for a given conversation ordered chronologically.
     *
     * // YB - 27-08-2026 code comment
     */
    public function getConversationMessages(Conversation $conversation, int $limit = 100): Collection
    {
        return $conversation->messages()
            ->with(['sender', 'replyTo.sender', 'reactions.user'])
            ->latest('created_at')
            ->take($limit)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Find existing direct 1-1 conversation between two users or create a new one atomically with locking.
     *
     * // YB - 27-08-2026 code comment
     */
    public function getOrCreateDirectConversation(User $user, int $recipientId): Conversation
    {
        if ($user->id === $recipientId) {
            throw new \InvalidArgumentException('Cannot create a conversation with yourself.');
        }

        return DB::transaction(function () use ($user, $recipientId) {
            // Look for existing direct conversation between these two users inside transaction with lock
            $existingConversation = Conversation::where('type', 'direct')
                ->whereHas('participants', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })
                ->whereHas('participants', function ($query) use ($recipientId) {
                    $query->where('users.id', $recipientId);
                })
                ->lockForUpdate()
                ->first();

            if ($existingConversation) {
                return $existingConversation->loadMissing(['participants', 'conversationUsers', 'latestMessage.sender']);
            }

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
                'last_delivered_at' => now(),
            ]);

            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $recipientId,
                'role' => 'member',
                'last_read_at' => null,
                'last_delivered_at' => null,
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
    public function createGroupConversation(
        User $creator,
        string $title,
        array $participantIds,
        ?string $description = null,
        bool $isPublic = false,
        ?UploadedFile $avatar = null
    ): Conversation {
        return DB::transaction(function () use ($creator, $title, $participantIds, $description, $isPublic, $avatar) {
            $avatarPath = null;
            if ($avatar) {
                $avatarPath = $avatar->store('group_avatars', 'public');
            }

            $conversation = Conversation::create([
                'type' => 'group',
                'title' => $title,
                'description' => $description,
                'avatar_path' => $avatarPath,
                'is_public' => $isPublic,
                'invite_code' => Conversation::generateUniqueInviteCode(),
                'last_message_at' => now(),
            ]);

            // Add creator as admin
            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $creator->id,
                'role' => 'admin',
                'last_read_at' => now(),
                'last_delivered_at' => now(),
            ]);

            // Add all other members
            $uniqueParticipantIds = array_unique(array_diff($participantIds, [$creator->id]));
            foreach ($uniqueParticipantIds as $participantId) {
                ConversationUser::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $participantId,
                    'role' => 'member',
                    'last_read_at' => null,
                    'last_delivered_at' => null,
                ]);
            }

            // Create initial system message announcing group creation
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $creator->id,
                'body' => "{$creator->name} created group \"{$title}\"",
                'type' => 'system',
            ]);

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
                'reply_to_id' => $data['reply_to_id'] ?? null,
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

            $message->load(['sender', 'replyTo.sender', 'reactions.user', 'conversation.participants', 'conversation.conversationUsers']);

            // Broadcast real-time event to all participants - YB - 26-08-2026
            try {
                broadcast(new MessageSent($message));
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
            ->update([
                'last_read_at' => $now,
                'last_delivered_at' => $now,
            ]);

        // Also update message read_at timestamp in database for direct chats
        if ($conversation->type === 'direct') {
            Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => $now, 'delivered_at' => $now]);
        }

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

        ConversationUser::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_delivered_at' => $now]);

        if ($conversation->type === 'direct') {
            Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $user->id)
                ->whereNull('delivered_at')
                ->update(['delivered_at' => $now]);
        }

        try {
            broadcast(new \App\Events\MessageDelivered($conversation->id, $user, $now))->toOthers();
        } catch (\Throwable $e) {
            Log::warning('Broadcast MessageDelivered failed: ' . $e->getMessage());
        }
    }

    /**
     * Synchronize and catch up delivery receipts across all conversations when user connects or sends heartbeat.
     *
     * // YB - 27-08-2026 code comment
     */
    public function syncDeliveredMessagesForUser(User $user): int
    {
        $now = now();
        $updatedConversationIds = [];

        // 1. Find all user's participant records
        $conversationUsers = ConversationUser::where('user_id', $user->id)->get();

        foreach ($conversationUsers as $cu) {
            $convId = $cu->conversation_id;

            // Check if there are messages from other senders that arrived after last_delivered_at
            $hasUndelivered = Message::where('conversation_id', $convId)
                ->where('sender_id', '!=', $user->id)
                ->when($cu->last_delivered_at, function ($q) use ($cu) {
                    $q->where('created_at', '>', $cu->last_delivered_at);
                })
                ->exists();

            if ($hasUndelivered || is_null($cu->last_delivered_at)) {
                $cu->update(['last_delivered_at' => $now]);
                $updatedConversationIds[] = $convId;
            }
        }

        // 2. Mark direct messages where this user is recipient as delivered
        Message::whereNull('delivered_at')
            ->where('sender_id', '!=', $user->id)
            ->whereIn('conversation_id', function ($query) use ($user) {
                $query->select('c.id')
                    ->from('conversations as c')
                    ->join('conversation_users as cu', 'cu.conversation_id', '=', 'c.id')
                    ->where('c.type', 'direct')
                    ->where('cu.user_id', $user->id);
            })
            ->update(['delivered_at' => $now]);

        // 3. Broadcast MessageDelivered event to notify senders in real-time
        foreach (array_unique($updatedConversationIds) as $conversationId) {
            try {
                broadcast(new \App\Events\MessageDelivered($conversationId, $user, $now))->toOthers();
            } catch (\Throwable $e) {
                Log::warning("Broadcast MessageDelivered failed for conv {$conversationId}: " . $e->getMessage());
            }
        }

        return count($updatedConversationIds);
    }

    /**
     * Promote or demote a group member's role.
     *
     * // YB - 26-08-2026 code comment
     */
    public function updateParticipantRole(Conversation $conversation, User $admin, int $targetUserId, string $role): void
    {
        if (! $conversation->isAdmin($admin->id)) {
            throw new AccessDeniedHttpException('Only group admins can change member roles.');
        }

        if (! in_array($role, ['admin', 'member'])) {
            throw new \InvalidArgumentException('Invalid role specified.');
        }

        $targetPivot = ConversationUser::where('conversation_id', $conversation->id)
            ->where('user_id', $targetUserId)
            ->first();

        if (! $targetPivot) {
            throw new \InvalidArgumentException('User is not a participant of this group.');
        }

        // If demoting from admin to member, make sure at least one other admin remains
        if ($targetPivot->role === 'admin' && $role === 'member') {
            $adminCount = ConversationUser::where('conversation_id', $conversation->id)
                ->where('role', 'admin')
                ->count();
            if ($adminCount <= 1) {
                throw new \InvalidArgumentException('The group must have at least one administrator.');
            }
        }

        $targetPivot->update(['role' => $role]);
        $targetUser = User::find($targetUserId);

        $roleText = $role === 'admin' ? 'an admin' : 'a member';
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'body' => "{$admin->name} changed {$targetUser->name}'s role to {$roleText}",
            'type' => 'system',
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);

        try {
            broadcast(new \App\Events\GroupRoleUpdated($conversation, $targetUserId, $role));
            broadcast(new MessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('Broadcast GroupRoleUpdated failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove a member from group.
     *
     * // YB - 26-08-2026 code comment
     */
    public function removeParticipant(Conversation $conversation, User $admin, int $targetUserId): void
    {
        if (! $conversation->isAdmin($admin->id)) {
            throw new AccessDeniedHttpException('Only group admins can remove members.');
        }

        $targetUser = User::findOrFail($targetUserId);

        ConversationUser::where('conversation_id', $conversation->id)
            ->where('user_id', $targetUserId)
            ->delete();

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $admin->id,
            'body' => "{$admin->name} removed {$targetUser->name} from the group",
            'type' => 'system',
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);

        try {
            broadcast(new \App\Events\GroupMemberRemoved($conversation, $targetUserId));
            broadcast(new MessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('Broadcast GroupMemberRemoved failed: ' . $e->getMessage());
        }
    }

    /**
     * Add new participants to group.
     *
     * // YB - 26-08-2026 code comment
     */
    public function addParticipants(Conversation $conversation, User $admin, array $userIds): void
    {
        if (! $conversation->isAdmin($admin->id)) {
            throw new AccessDeniedHttpException('Only group admins can add new members.');
        }

        $existingIds = $conversation->participants()->pluck('users.id')->all();
        $toAdd = array_diff($userIds, $existingIds);

        if (empty($toAdd)) {
            return;
        }

        $addedUsers = User::whereIn('id', $toAdd)->get();
        foreach ($addedUsers as $u) {
            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $u->id,
                'role' => 'member',
                'last_read_at' => null,
                'last_delivered_at' => null,
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $admin->id,
                'body' => "{$admin->name} added {$u->name} to the group",
                'type' => 'system',
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);

            try {
                broadcast(new \App\Events\GroupMemberJoined($conversation, $u));
                broadcast(new MessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('Broadcast GroupMemberJoined failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Leave group chat.
     *
     * // YB - 26-08-2026 code comment
     */
    public function leaveGroup(Conversation $conversation, User $user): void
    {
        if (! $conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('User is not a participant in this group.');
        }

        $wasAdmin = $conversation->isAdmin($user->id);

        ConversationUser::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->delete();

        // If leaving user was admin and no admins remain, promote oldest participant to admin
        if ($wasAdmin) {
            $hasAdmin = ConversationUser::where('conversation_id', $conversation->id)
                ->where('role', 'admin')
                ->exists();

            if (! $hasAdmin) {
                $oldestMember = ConversationUser::where('conversation_id', $conversation->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                if ($oldestMember) {
                    $oldestMember->update(['role' => 'admin']);
                    $promotedUser = User::find($oldestMember->user_id);
                    if ($promotedUser) {
                        try {
                            broadcast(new \App\Events\GroupRoleUpdated($conversation, $promotedUser->id, 'admin'));
                        } catch (\Throwable $e) {
                            Log::warning('Broadcast auto-promoted GroupRoleUpdated failed: ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => "{$user->name} left the group",
            'type' => 'system',
        ]);
        $conversation->update(['last_message_at' => $message->created_at]);

        try {
            broadcast(new \App\Events\GroupMemberRemoved($conversation, $user->id));
            broadcast(new MessageSent($message));
        } catch (\Throwable $e) {
            Log::warning('Broadcast leave MessageSent failed: ' . $e->getMessage());
        }
    }

    /**
     * Update group profile (title, description, is_public, avatar).
     *
     * // YB - 26-08-2026 code comment
     */
    public function updateGroupProfile(Conversation $conversation, User $admin, array $data, ?UploadedFile $avatar = null): Conversation
    {
        if (! $conversation->isAdmin($admin->id)) {
            throw new AccessDeniedHttpException('Only group admins can update group settings.');
        }

        $updateData = [];
        if (isset($data['title']) && trim($data['title'])) {
            $updateData['title'] = trim($data['title']);
        }
        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }
        if (array_key_exists('is_public', $data)) {
            $updateData['is_public'] = (bool) $data['is_public'];
        }
        if ($avatar) {
            if ($conversation->avatar_path && Storage::disk('public')->exists($conversation->avatar_path)) {
                Storage::disk('public')->delete($conversation->avatar_path);
            }
            $updateData['avatar_path'] = $avatar->store('group_avatars', 'public');
        }

        if (! empty($updateData)) {
            $conversation->update($updateData);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $admin->id,
                'body' => "{$admin->name} updated the group settings",
                'type' => 'system',
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);

            try {
                broadcast(new \App\Events\GroupUpdated($conversation));
                broadcast(new MessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('Broadcast GroupUpdated failed: ' . $e->getMessage());
            }
        }

        return $conversation->load(['participants', 'conversationUsers']);
    }

    /**
     * Reset group invite code.
     *
     * // YB - 26-08-2026 code comment
     */
    public function resetInviteCode(Conversation $conversation, User $admin): string
    {
        if (! $conversation->isAdmin($admin->id)) {
            throw new AccessDeniedHttpException('Only group admins can reset the invite link.');
        }

        $newCode = Conversation::generateUniqueInviteCode();
        $conversation->update(['invite_code' => $newCode]);

        try {
            broadcast(new \App\Events\GroupUpdated($conversation));
        } catch (\Throwable $e) {
            Log::warning('Broadcast resetInviteCode GroupUpdated failed: ' . $e->getMessage());
        }

        return $newCode;
    }

    /**
     * Join group via invite code (Direct join for public groups, Join Request for private).
     *
     * // YB - 26-08-2026 code comment
     */
    public function joinViaInviteCode(string $inviteCode, User $user): array
    {
        $conversation = Conversation::where('type', 'group')
            ->where('invite_code', $inviteCode)
            ->firstOrFail();

        // If user is already a member
        if ($conversation->isParticipant($user->id)) {
            return ['status' => 'already_member', 'conversation' => $conversation];
        }

        if ($conversation->is_public) {
            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'role' => 'member',
                'last_read_at' => now(),
                'last_delivered_at' => now(),
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => "{$user->name} joined via group link",
                'type' => 'system',
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);

            try {
                broadcast(new \App\Events\GroupMemberJoined($conversation, $user));
                broadcast(new MessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('Broadcast joinViaInviteCode failed: ' . $e->getMessage());
            }

            return ['status' => 'joined', 'conversation' => $conversation];
        }

        // Private group: Create or find pending join request
        $request = \App\Models\GroupJoinRequest::firstOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $user->id],
            ['status' => 'pending']
        );

        if ($request->status === 'approved') {
            return ['status' => 'already_member', 'conversation' => $conversation];
        }

        if ($request->status === 'rejected') {
            $request->update(['status' => 'pending']);
        }

        return ['status' => 'requested', 'conversation' => $conversation];
    }

    /**
     * Approve join request for private group.
     *
     * // YB - 26-08-2026 code comment
     */
    public function approveJoinRequest(Conversation $conversation, User $admin, int $requestId): void
    {
        if (! $conversation->isAdmin($admin->id)) {
            throw new AccessDeniedHttpException('Only group admins can approve join requests.');
        }

        $joinRequest = \App\Models\GroupJoinRequest::where('conversation_id', $conversation->id)
            ->where('id', $requestId)
            ->firstOrFail();

        $targetUser = $joinRequest->user;
        $joinRequest->update(['status' => 'approved']);

        if (! $conversation->isParticipant($targetUser->id)) {
            ConversationUser::create([
                'conversation_id' => $conversation->id,
                'user_id' => $targetUser->id,
                'role' => 'member',
                'last_read_at' => now(),
                'last_delivered_at' => now(),
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $admin->id,
                'body' => "{$admin->name} approved {$targetUser->name}'s request to join",
                'type' => 'system',
            ]);
            $conversation->update(['last_message_at' => $message->created_at]);

            try {
                broadcast(new \App\Events\GroupMemberJoined($conversation, $targetUser));
                broadcast(new MessageSent($message));
            } catch (\Throwable $e) {
                Log::warning('Broadcast approveJoinRequest failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Reject join request for private group.
     *
     * // YB - 26-08-2026 code comment
     */
    public function rejectJoinRequest(Conversation $conversation, User $admin, int $requestId): void
    {
        if (! $conversation->isAdmin($admin->id)) {
            throw new AccessDeniedHttpException('Only group admins can reject join requests.');
        }

        $joinRequest = \App\Models\GroupJoinRequest::where('conversation_id', $conversation->id)
            ->where('id', $requestId)
            ->firstOrFail();

        $joinRequest->update(['status' => 'rejected']);
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
     * // YB - 27-08-2026 code comment
     */
    public function deleteMessageForEveryone(User $user, Message $message): Message
    {
        if ($message->sender_id !== $user->id) {
            throw new AccessDeniedHttpException('You can only delete your own messages for everyone.');
        }

        // Clean up stored attachment file from disk
        if ($message->attachment_path && Storage::disk('public')->exists($message->attachment_path)) {
            Storage::disk('public')->delete($message->attachment_path);
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
     * // YB - 27-08-2026 code comment
     */
    public function deleteMessageForMe(User $user, Message $message): Message
    {
        if (! $message->conversation || ! $message->conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('You are not a participant in this conversation.');
        }

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

    /**
     * Toggle an emoji reaction on a message.
     *
     * // YB - 26-08-2026 code comment
     */
    public function toggleReaction(Message $message, User $user, string $emoji): array
    {
        $conversation = $message->conversation;
        if (! $conversation || ! $conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('You cannot react to messages in this conversation.');
        }

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        $action = 'added';
        if ($existing) {
            $existing->delete();
            $action = 'removed';
        } else {
            MessageReaction::create([
                'message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
        }

        // Fetch fresh reactions
        $message->load(['reactions.user']);
        $grouped = $message->reactions->groupBy('emoji');
        $reactionsData = [];
        foreach ($grouped as $em => $items) {
            $reactionsData[] = [
                'emoji' => $em,
                'count' => $items->count(),
                'user_ids' => $items->pluck('user_id')->all(),
                'users' => $items->map(fn($r) => ['id' => $r->user_id, 'name' => $r->user?->name ?? 'User'])->values()->all(),
                'has_reacted' => $items->contains('user_id', $user->id),
            ];
        }

        try {
            broadcast(new \App\Events\MessageReactionToggled($message, $user, $emoji, $action, $reactionsData));
        } catch (\Throwable $e) {
            Log::warning('Broadcast MessageReactionToggled failed: ' . $e->getMessage());
        }

        return $reactionsData;
    }

    /**
     * Toggle pinned status of a message.
     *
     * // YB - 26-08-2026 code comment
     */
    public function togglePinMessage(Message $message, User $user): Message
    {
        $conversation = $message->conversation;
        if (! $conversation || ! $conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('You cannot pin messages in this conversation.');
        }

        $isPinned = ! $message->is_pinned;
        $message->update([
            'is_pinned' => $isPinned,
            'pinned_at' => $isPinned ? now() : null,
        ]);

        $message->load(['sender', 'replyTo.sender', 'reactions.user', 'conversation']);

        try {
            broadcast(new \App\Events\MessagePinnedToggled($message, $user));
        } catch (\Throwable $e) {
            Log::warning('Broadcast MessagePinnedToggled failed: ' . $e->getMessage());
        }

        return $message;
    }

    /**
     * Get detailed read and delivery status per participant for a message.
     *
     * // YB - 26-08-2026 code comment
     */
    public function getMessageDeliveryInfo(Message $message, User $user): array
    {
        $conversation = $message->conversation;
        if (! $conversation || ! $conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('You cannot view message info for this conversation.');
        }

        $conversation->loadMissing(['participants', 'conversationUsers.user']);

        $participants = $conversation->participants->where('id', '!=', $message->sender_id);
        $conversationUsers = $conversation->conversationUsers->where('user_id', '!=', $message->sender_id);

        $readList = [];
        $deliveredList = [];

        $msgCreatedAt = $message->created_at;

        foreach ($participants as $participant) {
            $cu = $conversationUsers->firstWhere('user_id', $participant->id);
            $lastRead = $cu?->last_read_at;
            $lastDeliv = $cu?->last_delivered_at;

            $hasRead = $lastRead && $lastRead >= $msgCreatedAt;
            $hasDelivered = ($lastDeliv && $lastDeliv >= $msgCreatedAt) || $hasRead;

            if ($hasRead) {
                $readList[] = [
                    'user' => (new \App\Http\Resources\UserResource($participant))->resolve(request()),
                    'timestamp' => $lastRead->toISOString(),
                    'human_time' => $lastRead->diffForHumans(),
                ];
            } elseif ($hasDelivered) {
                $deliveredList[] = [
                    'user' => (new \App\Http\Resources\UserResource($participant))->resolve(request()),
                    'timestamp' => $lastDeliv ? $lastDeliv->toISOString() : $msgCreatedAt->toISOString(),
                    'human_time' => $lastDeliv ? $lastDeliv->diffForHumans() : 'Recently',
                ];
            }
        }

        $directRecipient = null;
        $directReadAt = null;
        $directDeliveredAt = null;

        if ($conversation->type === 'direct') {
            $otherParticipant = $participants->first();
            $cu = $conversationUsers->first();
            $directRecipient = $otherParticipant ? (new \App\Http\Resources\UserResource($otherParticipant))->resolve(request()) : null;

            $readTimestamp = $message->read_at ?? ($cu?->last_read_at && $cu->last_read_at >= $msgCreatedAt ? $cu->last_read_at : null);
            $delivTimestamp = $message->delivered_at ?? ($cu?->last_delivered_at && $cu->last_delivered_at >= $msgCreatedAt ? $cu->last_delivered_at : null);

            $directReadAt = $readTimestamp ? [
                'timestamp' => $readTimestamp->toISOString(),
                'formatted' => $readTimestamp->format('M j, Y \a\t g:i A'),
                'human_time' => $readTimestamp->diffForHumans(),
            ] : null;

            $directDeliveredAt = ($delivTimestamp || $readTimestamp) ? [
                'timestamp' => ($delivTimestamp ?? $readTimestamp)->toISOString(),
                'formatted' => ($delivTimestamp ?? $readTimestamp)->format('M j, Y \a\t g:i A'),
                'human_time' => ($delivTimestamp ?? $readTimestamp)->diffForHumans(),
            ] : null;
        }

        return [
            'message_id' => $message->id,
            'is_direct' => $conversation->type === 'direct',
            'body' => $message->body,
            'type' => $message->type,
            'attachment_name' => $message->attachment_name,
            'created_at' => $message->created_at?->toISOString(),
            'created_at_formatted' => $message->created_at?->format('M j, Y \a\t g:i A'),
            'created_at_human' => $message->created_at?->diffForHumans(),
            'direct_recipient' => $directRecipient,
            'direct_read_at' => $directReadAt,
            'direct_delivered_at' => $directDeliveredAt,
            'read_by' => $readList,
            'delivered_to' => $deliveredList,
            'total_participants' => $participants->count(),
        ];
    }
}
