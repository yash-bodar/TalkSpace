<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupRoleUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public int $userId;
    public string $role;
    public array $participantIds;

    /**
     * Create a new event instance.
     *
     * // YB - 26-08-2026 code comment
     */
    public function __construct(Conversation $conversation, int $userId, string $role)
    {
        $this->conversationId = $conversation->id;
        $this->userId = $userId;
        $this->role = $role;
        $this->participantIds = $conversation->participants()->pluck('users.id')->all();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * // YB - 26-08-2026 code comment
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.' . $this->conversationId),
        ];

        foreach ($this->participantIds as $pid) {
            $channels[] = new PrivateChannel('user.' . $pid);
        }

        return array_values(array_unique($channels, SORT_REGULAR));
    }

    /**
     * The event's broadcast name.
     *
     * // YB - 26-08-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'group.role.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * // YB - 26-08-2026 code comment
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'user_id' => $this->userId,
            'role' => $this->role,
        ];
    }
}
