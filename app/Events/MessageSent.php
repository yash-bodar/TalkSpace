<?php

namespace App\Events;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    /**
     * Create a new event instance.
     *
     * // YB - 24-08-2026 code comment
     */
    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing(['sender', 'conversation.participants']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * // YB - 24-08-2026 code comment
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('chat.' . $this->message->conversation_id),
        ];

        // Also broadcast to each participant's private user channel to trigger sidebar update & badge
        foreach ($this->message->conversation->participants as $participant) {
            $channels[] = new PrivateChannel('user.' . $participant->id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     *
     * // YB - 24-08-2026 code comment
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * // YB - 24-08-2026 code comment
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => (new MessageResource($this->message))->resolve(),
        ];
    }
}
