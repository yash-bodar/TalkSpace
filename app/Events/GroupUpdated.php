<?php

namespace App\Events;

use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;

    /**
     * Create a new event instance.
     *
     * // YB - 26-08-2026 code comment
     */
    public function __construct(Conversation $conversation)
    {
        $this->conversation = $conversation->loadMissing(['participants', 'conversationUsers']);
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
            new PrivateChannel('chat.' . $this->conversation->id),
        ];

        foreach ($this->conversation->participants as $participant) {
            $channels[] = new PrivateChannel('user.' . $participant->id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     *
     * // YB - 26-08-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'group.updated';
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
            'conversation' => (new ConversationResource($this->conversation))->resolve(request()),
        ];
    }
}
