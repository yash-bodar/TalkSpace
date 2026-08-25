<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserHeartbeat implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $userName;
    public string $lastActiveAt;

    /**
     * Create a new event instance.
     *
     * // YB - 25-08-2026 code comment
     */
    public function __construct(User $user)
    {
        $this->userId = $user->id;
        $this->userName = $user->name;
        $this->lastActiveAt = now()->toISOString();
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
            new Channel('online-heartbeats'),
        ];
    }

    /**
     * Broadcast event name.
     *
     * // YB - 25-08-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'user.heartbeat';
    }

    /**
     * Get data payload.
     *
     * // YB - 25-08-2026 code comment
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'name' => $this->userName,
            'timestamp' => now()->timestamp,
        ];
    }
}
