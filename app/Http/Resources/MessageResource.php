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
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender' => new UserResource($this->whenLoaded('sender')),
            'body' => $this->body,
            'type' => $this->type,
            'attachment_url' => $this->attachment_url,
            'attachment_name' => $this->attachment_name,
            'attachment_size' => $this->attachment_size,
            'read_at' => $this->read_at?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'is_edited' => (bool) $this->is_edited,
            'edited_at' => $this->edited_at?->toISOString(),
            'is_deleted_for_everyone' => (bool) $this->is_deleted_for_everyone,
            'deleted_for_user_ids' => $this->deleted_for_user_ids ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
