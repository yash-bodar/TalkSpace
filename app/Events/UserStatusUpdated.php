<?php

namespace App\Events;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * // YB - 14-09-2026 code comment
     */
    public function __construct(
        public User $user
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
            new PresenceChannel('online'),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * // YB - 14-09-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'UserStatusUpdated';
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
            'user' => (new UserResource($this->user))->resolve(request()),
        ];
    }
}
