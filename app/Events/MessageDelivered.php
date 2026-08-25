<?php

namespace App\Events;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDelivered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public int $userId;
    public string $deliveredAt;

    /**
     * Create a new event instance.
     *
     * // YB - 25-08-2026 code comment
     */
    public function __construct(int $conversationId, User $user, ?Carbon $deliveredAt = null)
    {
        $this->conversationId = $conversationId;
        $this->userId = $user->id;
        $this->deliveredAt = ($deliveredAt ?? now())->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * // YB - 25-08-2026 code comment
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.' . $this->conversationId),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * // YB - 25-08-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'message.delivered';
    }

    /**
     * Get the data to broadcast.
     *
     * // YB - 25-08-2026 code comment
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'delivered_at' => $this->deliveredAt,
        ];
    }
}
