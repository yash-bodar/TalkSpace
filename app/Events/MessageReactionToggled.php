<?php

namespace App\Events;

use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReactionToggled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $messageId;
    public int $conversationId;
    public array $reactions;
    public int $userId;
    public string $emoji;
    public string $action; // 'added' or 'removed'

    /**
     * Create a new event instance.
     *
     * // YB - 26-08-2026 code comment
     */
    public function __construct(Message $message, User $user, string $emoji, string $action, array $reactions)
    {
        $this->messageId = $message->id;
        $this->conversationId = $message->conversation_id;
        $this->userId = $user->id;
        $this->emoji = $emoji;
        $this->action = $action;
        $this->reactions = $reactions;
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
        return 'message.reaction.toggled';
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
            'user_id' => $this->userId,
            'emoji' => $this->emoji,
            'action' => $this->action,
            'reactions' => $this->reactions,
        ];
    }
}
