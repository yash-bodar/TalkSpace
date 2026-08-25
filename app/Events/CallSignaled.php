<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignaled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $conversationId;
    public User $caller;
    public string $type; // 'offer', 'answer', 'candidate', 'hangup', 'rejected', 'missed'
    public string $callType; // 'audio', 'video'
    public mixed $payload;

    /**
     * Create a new event instance.
     *
     * // YB - 25-08-2026 code comment
     */
    public function __construct(int $conversationId, User $caller, string $type, string $callType = 'audio', mixed $payload = null)
    {
        $this->conversationId = $conversationId;
        $this->caller = $caller;
        $this->type = $type;
        $this->callType = $callType;
        $this->payload = $payload;
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
        return 'call.signaled';
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
            'caller' => [
                'id' => $this->caller->id,
                'name' => $this->caller->name,
                'avatar_url' => $this->caller->avatar_url,
            ],
            'type' => $this->type,
            'call_type' => $this->callType,
            'payload' => $this->payload,
        ];
    }
}
