<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateConversationRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Dependency injection of ChatService.
     *
     * // YB - 24-08-2026 code comment
     */
    public function __construct(
        protected ChatService $chatService
    ) {}

    /**
     * Display the main chat interface and conversation list.
     *
     * // YB - 24-08-2026 code comment
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $user->updateQuietly(['last_active_at' => now()]);
        $this->chatService->syncDeliveredMessagesForUser($user);

        $conversations = $this->chatService->getUserConversations($user);

        return Inertia::render('Chat/Index', [
            'conversations' => ConversationResource::collection($conversations)->resolve($request),
            'activeConversation' => null,
            'messages' => [],
        ]);
    }

    /**
     * Display a specific active conversation with message history.
     *
     * // YB - 24-08-2026 code comment
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $user->updateQuietly(['last_active_at' => now()]);
        $this->chatService->syncDeliveredMessagesForUser($user);

        $isMember = $conversation->isParticipant($user->id);

        if ($isMember) {
            // Mark active conversation messages as read FIRST
            $this->chatService->markConversationAsRead($user, $conversation);
        }

        $conversations = $this->chatService->getUserConversations($user);
        $conversation->loadMissing(['participants', 'conversationUsers', 'latestMessage.sender']);
        
        $aroundMessageId = $request->query('around_message_id') ? (int) $request->query('around_message_id') : null;

        // Only load messages if user is an active member, protecting private group chat history
        // YB - 14-09-2026 Support jumping to historical message window
        $messages = $isMember ? $this->chatService->getConversationMessages($conversation, 100, $aroundMessageId) : collect();

        return Inertia::render('Chat/Index', [
            'conversations' => ConversationResource::collection($conversations)->resolve($request),
            'activeConversation' => (new ConversationResource($conversation))->resolve($request),
            'messages' => MessageResource::collection($messages)->resolve($request),
        ]);
    }

    /**
     * Create a new direct or group conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function storeConversation(CreateConversationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $type = $request->validated('type');

        if ($type === 'direct') {
            $conversation = $this->chatService->getOrCreateDirectConversation(
                $user,
                (int) $request->validated('recipient_id')
            );
        } else {
            $conversation = $this->chatService->createGroupConversation(
                $user,
                (string) $request->validated('title'),
                (array) $request->validated('participant_ids'),
                $request->validated('description'),
                (bool) $request->validated('is_public', false),
                $request->file('avatar')
            );
        }

        return redirect()->route('chat.show', $conversation->id);
    }

    /**
     * Send a message in a conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function storeMessage(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $message = $this->chatService->sendMessage(
            $request->user(),
            $conversation,
            $request->validated(),
            $request->file('attachment')
        );

        return response()->json([
            'message' => (new MessageResource($message))->resolve($request),
        ], 201);
    }

    /**
     * Mark a conversation as read.
     *
     * // YB - 24-08-2026 code comment
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $this->chatService->markConversationAsRead($request->user(), $conversation);

        return response()->json(['success' => true]);
    }

    /**
     * Mark conversation messages as delivered.
     *
     * // YB - 25-08-2026 code comment
     */
    public function markAsDelivered(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $this->chatService->markConversationAsDelivered($request->user(), $conversation);

        return response()->json(['success' => true]);
    }

    /**
     * Broadcast typing status.
     *
     * // YB - 24-08-2026 code comment
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $isTyping = (bool) $request->input('is_typing', true);
        $this->chatService->broadcastTyping($request->user(), $conversation, $isTyping);

        return response()->json(['success' => true]);
    }

    /**
     * Search available users to start chats.
     *
     * // YB - 24-08-2026 code comment
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $users = $this->chatService->searchUsers(
            $request->user(),
            $request->query('query')
        );

        return response()->json([
            'users' => UserResource::collection($users),
        ]);
    }

    /**
     * Broadcast user activity heartbeat and sync delivery for all pending unread messages.
     *
     * // YB - 27-08-2026 code comment
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->updateQuietly(['last_active_at' => now()]);

        try {
            broadcast(new \App\Events\UserHeartbeat($user))->toOthers();
        } catch (\Throwable $e) {
            // Silently handle if broker briefly disconnected
        }

        $deliveredCount = $this->chatService->syncDeliveredMessagesForUser($user);

        return response()->json([
            'success' => true,
            'delivered_sync_count' => $deliveredCount,
        ]);
    }

    /**
     * Edit message text.
     *
     * // YB - 27-08-2026 code comment
     */
    public function updateMessage(Request $request, \App\Models\Message $message): JsonResponse
    {
        Gate::authorize('update', $message);

        $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $updated = $this->chatService->editMessage($request->user(), $message, (string) $request->input('body'));

        return response()->json([
            'message' => (new MessageResource($updated))->resolve($request),
        ]);
    }

    /**
     * Delete message (for me or for everyone).
     *
     * // YB - 27-08-2026 code comment
     */
    public function deleteMessage(Request $request, \App\Models\Message $message): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:me,everyone'],
        ]);

        $type = (string) $request->input('type', 'me');
        Gate::authorize('delete', [$message, $type]);

        if ($type === 'everyone') {
            $updated = $this->chatService->deleteMessageForEveryone($request->user(), $message);
        } else {
            $updated = $this->chatService->deleteMessageForMe($request->user(), $message);
        }

        return response()->json([
            'message' => (new MessageResource($updated))->resolve($request),
        ]);
    }

    /**
     * Add members to group.
     *
     * // YB - 26-08-2026 code comment
     */
    public function addMembers(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->chatService->addParticipants($conversation, $request->user(), (array) $request->input('user_ids'));

        $conversation->load(['participants', 'conversationUsers']);

        return response()->json([
            'conversation' => (new ConversationResource($conversation))->resolve($request),
            'message' => 'Members added successfully',
        ]);
    }

    /**
     * Remove a member from group.
     *
     * // YB - 26-08-2026 code comment
     */
    public function removeMember(Request $request, Conversation $conversation, \App\Models\User $user): JsonResponse
    {
        $this->chatService->removeParticipant($conversation, $request->user(), $user->id);

        $conversation->load(['participants', 'conversationUsers']);

        return response()->json([
            'conversation' => (new ConversationResource($conversation))->resolve($request),
            'message' => 'Member removed successfully',
        ]);
    }

    /**
     * Update participant role (admin/member).
     *
     * // YB - 26-08-2026 code comment
     */
    public function updateMemberRole(Request $request, Conversation $conversation, \App\Models\User $user): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'in:admin,member'],
        ]);

        $this->chatService->updateParticipantRole($conversation, $request->user(), $user->id, (string) $request->input('role'));

        $conversation->load(['participants', 'conversationUsers']);

        return response()->json([
            'conversation' => (new ConversationResource($conversation))->resolve($request),
            'message' => 'Role updated successfully',
        ]);
    }

    /**
     * Leave group chat.
     *
     * // YB - 26-08-2026 code comment
     */
    public function leaveGroup(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->chatService->leaveGroup($conversation, $request->user());

        return redirect()->route('chat.index')->with('success', 'You left the group.');
    }

    /**
     * Update group profile settings.
     *
     * // YB - 26-08-2026 code comment
     */
    public function updateGroupSettings(Request $request, Conversation $conversation): JsonResponse
    {
        $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['nullable', 'boolean'],
            'avatar' => ['nullable', 'image', 'max:5120'], // 5MB max
        ]);

        $updated = $this->chatService->updateGroupProfile(
            $conversation,
            $request->user(),
            $request->only(['title', 'description', 'is_public']),
            $request->file('avatar')
        );

        return response()->json([
            'conversation' => (new ConversationResource($updated))->resolve($request),
            'message' => 'Group settings updated successfully',
        ]);
    }

    /**
     * Reset group invite code.
     *
     * // YB - 26-08-2026 code comment
     */
    public function resetInviteCode(Request $request, Conversation $conversation): JsonResponse
    {
        $newCode = $this->chatService->resetInviteCode($conversation, $request->user());

        return response()->json([
            'invite_code' => $newCode,
            'invite_url' => route('chat.join', $newCode),
            'message' => 'Invite link reset successfully',
        ]);
    }

    /**
     * Join group via invite link.
     *
     * // YB - 26-08-2026 code comment
     */
    public function joinGroup(Request $request, string $invite_code): RedirectResponse
    {
        $user = $request->user();
        $result = $this->chatService->joinViaInviteCode($invite_code, $user);

        if (isset($result['conversation'])) {
            return redirect()->route('chat.show', $result['conversation']->id);
        }

        return redirect()->route('chat.index')->with('info', 'Join request submitted to group administrators.');
    }

    /**
     * Approve join request.
     *
     * // YB - 26-08-2026 code comment
     */
    public function approveJoinRequest(Request $request, Conversation $conversation, int $joinRequestId): JsonResponse
    {
        $this->chatService->approveJoinRequest($conversation, $request->user(), $joinRequestId);

        $conversation->load(['participants', 'conversationUsers', 'joinRequests.user']);

        return response()->json([
            'conversation' => (new ConversationResource($conversation))->resolve($request),
            'message' => 'Join request approved',
        ]);
    }

    /**
     * Reject join request.
     *
     * // YB - 26-08-2026 code comment
     */
    public function rejectJoinRequest(Request $request, Conversation $conversation, int $joinRequestId): JsonResponse
    {
        $this->chatService->rejectJoinRequest($conversation, $request->user(), $joinRequestId);

        $conversation->load(['participants', 'conversationUsers', 'joinRequests.user']);

        return response()->json([
            'conversation' => (new ConversationResource($conversation))->resolve($request),
            'message' => 'Join request rejected',
        ]);
    }

    /**
     * Toggle an emoji reaction on a message.
     *
     * // YB - 26-08-2026 code comment
     */
    public function toggleReaction(Request $request, Message $message): JsonResponse
    {
        $request->validate([
            'emoji' => ['required', 'string', 'max:32'],
        ]);

        $reactions = $this->chatService->toggleReaction($message, $request->user(), $request->input('emoji'));

        return response()->json([
            'message_id' => $message->id,
            'reactions' => $reactions,
        ]);
    }

    /**
     * Toggle pinned status of a message.
     *
     * // YB - 26-08-2026 code comment
     */
    public function togglePinMessage(Request $request, Message $message): JsonResponse
    {
        $updatedMessage = $this->chatService->togglePinMessage($message, $request->user());

        return response()->json([
            'message' => (new MessageResource($updatedMessage))->resolve($request),
        ]);
    }

    /**
     * Retrieve detailed read and delivery status per participant for a message.
     *
     * // YB - 26-08-2026 code comment
     */
    public function getMessageDeliveryInfo(Request $request, Message $message): JsonResponse
    {
        $info = $this->chatService->getMessageDeliveryInfo($message, $request->user());

        return response()->json($info);
    }

    /**
     * Transmit WebRTC call signal payload to other participants.
     *
     * // YB - 27-08-2026 code comment
     */
    public function signalCall(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:offer,answer,candidate,hangup,rejected'],
            'call_type' => ['nullable', 'string', 'in:audio,video'],
            'payload' => ['nullable'],
        ]);

        $this->chatService->signalCall($request->user(), $conversation, $validated);

        return response()->json(['success' => true]);
    }

    /**
     * Log a call status / duration message in the conversation stream.
     *
     * // YB - 27-08-2026 code comment
     */
    public function logCall(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $request->validate([
            'status' => ['required', 'string', 'in:missed,completed,declined'],
            'call_type' => ['required', 'string', 'in:audio,video'],
            'duration' => ['nullable', 'string', 'max:50'],
        ]);

        $message = $this->chatService->logCallMessage(
            $request->user(),
            $conversation,
            (string) $request->input('status'),
            (string) $request->input('call_type'),
            $request->input('duration')
        );

        return response()->json([
            'message' => (new MessageResource($message))->resolve($request),
        ]);
    }

    /**
     * Store and broadcast a voice note audio recording.
     *
     * // YB - 14-09-2026 code comment
     */
    public function storeVoiceNote(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $request->validate([
            'audio' => ['required', 'file', 'mimes:webm,ogg,mp3,wav,m4a,mp4', 'max:20480'], // 20MB max
            'duration' => ['required', 'integer', 'min:1', 'max:3600'],
            'waveform' => ['nullable', 'array'],
            'waveform.*' => ['integer', 'min:0', 'max:100'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        $message = $this->chatService->sendVoiceNote(
            $request->user(),
            $conversation,
            $request->file('audio'),
            (int) $request->input('duration'),
            (array) $request->input('waveform', []),
            $request->input('reply_to_id') ? (int) $request->input('reply_to_id') : null
        );

        return response()->json([
            'message' => (new MessageResource($message))->resolve($request),
        ], 201);
    }

    /**
     * Search historical messages across user's conversations using full-text search.
     *
     * // YB - 14-09-2026 code comment
     */
    public function searchMessages(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:255'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ]);

        $messages = $this->chatService->searchMessages(
            $request->user(),
            (string) $request->query('query'),
            $request->query('conversation_id') ? (int) $request->query('conversation_id') : null
        );

        return response()->json([
            'messages' => MessageResource::collection($messages)->resolve($request),
        ]);
    }

    /**
     * Forward an existing message to one or more conversations.
     *
     * // YB - 14-09-2026 code comment
     */
    public function forwardMessage(Request $request, Message $message): JsonResponse
    {
        $request->validate([
            'conversation_ids' => ['required', 'array', 'min:1', 'max:10'],
            'conversation_ids.*' => ['required', 'integer', 'exists:conversations,id'],
        ]);

        $forwarded = $this->chatService->forwardMessage(
            $request->user(),
            $message,
            (array) $request->input('conversation_ids')
        );

        return response()->json([
            'messages' => MessageResource::collection(collect($forwarded))->resolve($request),
            'count' => count($forwarded),
            'message' => 'Message forwarded successfully',
        ]);
    }

    /**
     * Toggle pinned status of conversation.
     *
     * // YB - 14-09-2026 code comment
     */
    public function togglePinConversation(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $result = $this->chatService->togglePinConversation($request->user(), $conversation);

        return response()->json($result);
    }

    /**
     * Toggle archived status of conversation.
     *
     * // YB - 14-09-2026 code comment
     */
    public function toggleArchiveConversation(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $result = $this->chatService->toggleArchiveConversation($request->user(), $conversation);

        return response()->json($result);
    }

    /**
     * Update mute notifications settings for conversation.
     *
     * // YB - 14-09-2026 code comment
     */
    public function updateMuteSettings(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $request->validate([
            'duration' => ['nullable', 'string', 'in:unmute,8hours,1week,always'],
        ]);

        $result = $this->chatService->updateMuteSettings(
            $request->user(),
            $conversation,
            $request->input('duration')
        );

        return response()->json($result);
    }

    /**
     * Update user profile status message.
     *
     * // YB - 14-09-2026 code comment
     */
    public function updateUserStatus(Request $request): JsonResponse
    {
        $request->validate([
            'status_text' => ['nullable', 'string', 'max:255'],
            'status_emoji' => ['nullable', 'string', 'max:32'],
            'expires_in_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
        ]);

        $user = $this->chatService->updateUserStatus(
            $request->user(),
            $request->input('status_text'),
            $request->input('status_emoji'),
            $request->input('expires_in_minutes') ? (int) $request->input('expires_in_minutes') : null
        );

        return response()->json([
            'user' => (new UserResource($user))->resolve($request),
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * Save Web Push subscription.
     *
     * // YB - 14-09-2026 code comment
     */
    public function savePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'subscription' => ['required', 'array'],
            'subscription.endpoint' => ['required', 'string'],
        ]);

        $this->chatService->savePushSubscription($request->user(), (array) $request->input('subscription'));

        return response()->json(['success' => true]);
    }
}

