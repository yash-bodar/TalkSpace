<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationPinned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * // YB - 14-09-2026 code comment
     */
    public function __construct(
        public int $conversationId,
        public User $user,
        public bool $isPinned,
        public ?string $pinnedAt = null
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * // YB - 14-09-2026 code comment
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * // YB - 14-09-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'ConversationPinned';
    }

    /**
     * Get the data to broadcast.
     *
     * // YB - 14-09-2026 code comment
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'is_pinned' => $this->isPinned,
            'pinned_at' => $this->pinnedAt,
        ];
    }
}
