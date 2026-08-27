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
        
        // Only load messages if user is an active member, protecting private group chat history
        $messages = $isMember ? $this->chatService->getConversationMessages($conversation, 100) : collect();

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
     * // YB - 25-08-2026 code comment
     */
    public function updateMessage(Request $request, \App\Models\Message $message): JsonResponse
    {
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
     * // YB - 25-08-2026 code comment
     */
    public function deleteMessage(Request $request, \App\Models\Message $message): JsonResponse
    {
        $type = $request->input('type', 'me'); // 'me' | 'everyone'

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
}
