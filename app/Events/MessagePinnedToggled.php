<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessagePinnedToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $messageId;
    public int $conversationId;
    public bool $isPinned;
    public ?array $messageData;
    public int $userId;

    /**
     * Create a new event instance.
     *
     * // YB - 26-08-2026 code comment
     */
    public function __construct(Message $message, User $user)
    {
        $this->messageId = $message->id;
        $this->conversationId = $message->conversation_id;
        $this->isPinned = (bool) $message->is_pinned;
        $this->userId = $user->id;
        $this->messageData = (new MessageResource($message->loadMissing(['sender', 'replyTo.sender', 'reactions.user'])))->resolve(request());
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * // YB - 26-08-2026 code comment
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->conversationId),
        ];
    }

    /**
     * Broadcast event name.
     *
     * // YB - 26-08-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'message.pinned.toggled';
    }

    /**
     * Broadcast payload.
     *
     * // YB - 26-08-2026 code comment
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'conversation_id' => $this->conversationId,
            'is_pinned' => $this->isPinned,
            'user_id' => $this->userId,
            'message' => $this->messageData,
        ];
    }
}
