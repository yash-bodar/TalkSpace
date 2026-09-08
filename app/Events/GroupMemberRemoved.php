<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMemberRemoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public int $removedUserId;
    public array $remainingParticipantIds;

    /**
     * Create a new event instance.
     *
     * // YB - 26-08-2026 code comment
     */
    public function __construct(Conversation $conversation, int $removedUserId)
    {
        $this->conversationId = $conversation->id;
        $this->removedUserId = $removedUserId;
        $this->remainingParticipantIds = $conversation->participants()->pluck('users.id')->all();
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
            new PrivateChannel('user.' . $this->removedUserId),
        ];

        foreach ($this->remainingParticipantIds as $pid) {
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
        return 'group.member.removed';
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
            'removed_user_id' => $this->removedUserId,
        ];
    }
}
