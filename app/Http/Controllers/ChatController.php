<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateConversationRequest;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
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

        // Mark active conversation messages as read FIRST
        $this->chatService->markConversationAsRead($user, $conversation);

        $conversations = $this->chatService->getUserConversations($user);
        $conversation->loadMissing(['participants', 'conversationUsers', 'latestMessage.sender']);
        $messages = $this->chatService->getConversationMessages($conversation, 100);

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
                (array) $request->validated('participant_ids')
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
     * Broadcast user activity heartbeat.
     *
     * // YB - 25-08-2026 code comment
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

        return response()->json(['success' => true]);
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
}
