<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * // YB - 24-08-2026 code comment
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $authUserId = $request->user()?->id;
        $directRecipient = $this->type === 'direct' ? $this->getDirectRecipient($authUserId) : null;
        
        $title = $this->type === 'direct'
            ? ($directRecipient?->name ?? 'Conversation')
            : ($this->title ?? 'Group Chat');

        $avatar = $this->type === 'direct'
            ? ($directRecipient?->avatar_url ?? null)
            : 'https://ui-avatars.com/api/?name=' . urlencode($title) . '&background=D91A8D&color=fff&bold=true';

        // Get user's last_read_at timestamp from pivot or relation
        $lastReadAt = null;
        if ($this->relationLoaded('conversationUsers') && $this->conversationUsers) {
            $userPivot = $this->conversationUsers->firstWhere('user_id', $authUserId);
            $lastReadAt = $userPivot?->last_read_at;
        } elseif ($this->relationLoaded('participants') && $this->participants) {
            $currentParticipant = $this->participants->firstWhere('id', $authUserId);
            $lastReadAt = $currentParticipant?->pivot?->last_read_at;
        }

        // Calculate unread message count
        // YB - 08-09-2026 code comment
        $unreadCount = 0;
        if ($this->relationLoaded('messages') && $this->messages) {
            $unreadCount = $this->messages
                ->where('sender_id', '!=', $authUserId)
                ->filter(function ($m) use ($lastReadAt) {
                    if ($lastReadAt === null) return true;
                    return $m->created_at > $lastReadAt;
                })
                ->count();
        } elseif ($authUserId) {
            $unreadQuery = $this->messages()->where('sender_id', '!=', $authUserId);
            if ($lastReadAt) {
                $unreadQuery->where('created_at', '>', $lastReadAt);
            }
            $unreadCount = $unreadQuery->count();
        }

        $latestMsg = null;
        if ($this->relationLoaded('latestMessage') && $this->latestMessage) {
            $latestMsg = $this->latestMessage;
        } elseif ($this->relationLoaded('messages') && $this->messages && $this->messages->isNotEmpty()) {
            $latestMsg = $this->messages->last();
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $title,
            'avatar_url' => $avatar,
            'direct_recipient' => $directRecipient ? (new UserResource($directRecipient))->resolve($request) : null,
            'participants' => $this->relationLoaded('participants')
                ? UserResource::collection($this->participants)->resolve($request)
                : [],
            'latest_message' => $latestMsg ? (new MessageResource($latestMsg))->resolve($request) : null,
            'unread_count' => $unreadCount,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
