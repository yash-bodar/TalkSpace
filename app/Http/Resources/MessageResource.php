<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * // YB - 24-08-2026 code comment
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conv = $this->relationLoaded('conversation') ? $this->conversation : null;
        $isGroup = $conv && $conv->type === 'group';

        $deliveredToAll = false;
        $readByAll = false;

        if ($isGroup && ($conv->relationLoaded('conversationUsers') || $conv->relationLoaded('participants'))) {
            $otherUsers = $conv->relationLoaded('conversationUsers')
                ? $conv->conversationUsers->where('user_id', '!=', $this->sender_id)
                : $conv->participants->where('id', '!=', $this->sender_id);

            if ($otherUsers->isNotEmpty()) {
                $msgCreatedAt = $this->created_at ? ($this->created_at instanceof \Carbon\CarbonInterface ? $this->created_at : \Illuminate\Support\Carbon::parse($this->created_at)) : now();

                $deliveredToAll = $otherUsers->every(function ($u) use ($msgCreatedAt) {
                    $pivot = $u->pivot ?? $u;
                    $delivAt = $pivot->last_delivered_at ? ($pivot->last_delivered_at instanceof \Carbon\CarbonInterface ? $pivot->last_delivered_at : \Illuminate\Support\Carbon::parse($pivot->last_delivered_at)) : null;
                    $readAt = $pivot->last_read_at ? ($pivot->last_read_at instanceof \Carbon\CarbonInterface ? $pivot->last_read_at : \Illuminate\Support\Carbon::parse($pivot->last_read_at)) : null;

                    return ($delivAt && $delivAt >= $msgCreatedAt) || ($readAt && $readAt >= $msgCreatedAt);
                });

                $readByAll = $otherUsers->every(function ($u) use ($msgCreatedAt) {
                    $pivot = $u->pivot ?? $u;
                    $readAt = $pivot->last_read_at ? ($pivot->last_read_at instanceof \Carbon\CarbonInterface ? $pivot->last_read_at : \Illuminate\Support\Carbon::parse($pivot->last_read_at)) : null;

                    return $readAt && $readAt >= $msgCreatedAt;
                });
            } else {
                $deliveredToAll = false;
                $readByAll = false;
            }
        } else {
            $deliveredToAll = ! empty($this->delivered_at);
            $readByAll = ! empty($this->read_at);
        }

        // Quoted message data (Reply preview) - YB - 26-08-2026
        $replyToData = null;
        if ($this->relationLoaded('replyTo') && $this->replyTo) {
            $replyToData = [
                'id' => $this->replyTo->id,
                'sender_id' => $this->replyTo->sender_id,
                'sender_name' => $this->replyTo->sender?->name ?? 'User',
                'body' => $this->replyTo->body,
                'type' => $this->replyTo->type,
                'attachment_name' => $this->replyTo->attachment_name,
                'attachment_url' => $this->replyTo->attachment_url,
                'is_deleted_for_everyone' => (bool) $this->replyTo->is_deleted_for_everyone,
            ];
        }

        // Aggregated emoji reactions - YB - 26-08-2026
        $reactionsData = [];
        if ($this->relationLoaded('reactions') && $this->reactions) {
            $grouped = $this->reactions->groupBy('emoji');
            foreach ($grouped as $emoji => $items) {
                $reactionsData[] = [
                    'emoji' => $emoji,
                    'count' => $items->count(),
                    'user_ids' => $items->pluck('user_id')->all(),
                    'users' => $items->map(function ($r) {
                        return [
                            'id' => $r->user_id,
                            'name' => $r->user?->name ?? 'User',
                        ];
                    })->values()->all(),
                    'has_reacted' => $request->user() ? $items->contains('user_id', $request->user()->id) : false,
                ];
            }
        }

        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'reply_to_id' => $this->reply_to_id,
            'reply_to' => $replyToData,
            'sender_id' => $this->sender_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'body' => $this->body,
            'type' => $this->type,
            'attachment_url' => $this->attachment_url,
            'attachment_name' => $this->attachment_name,
            'attachment_size' => $this->attachment_size,
            'read_at' => $this->read_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            // YB - 26-08-2026 - Multi-user group delivery & read status flags
            'delivered_to_all' => (bool) $deliveredToAll,
            'read_by_all' => (bool) $readByAll,
            'is_edited' => (bool) $this->is_edited,
            'edited_at' => $this->edited_at?->toISOString(),
            'is_deleted_for_everyone' => (bool) $this->is_deleted_for_everyone,
            'deleted_for_user_ids' => $this->deleted_for_user_ids ?? [],
            'is_pinned' => (bool) $this->is_pinned,
            'pinned_at' => $this->pinned_at?->toISOString(),
            'reactions' => $reactionsData,
            'created_at' => $this->created_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
