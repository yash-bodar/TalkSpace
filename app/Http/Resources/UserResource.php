<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'last_active_at' => $this->last_active_at?->toISOString(),
            'last_active_human' => $this->last_active_at ? (
                $this->last_active_at->isToday()
                    ? 'today at ' . $this->last_active_at->format('g:i A')
                    : ($this->last_active_at->isYesterday()
                        ? 'yesterday at ' . $this->last_active_at->format('g:i A')
                        : $this->last_active_at->format('M d \a\t g:i A'))
            ) : 'recently',
        ];
    }
}
