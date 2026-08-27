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
            // YB - 26-08-2026 format last active timestamp according to timeframe rules
            'last_active_human' => $this->formatLastActiveHuman(),
        ];
    }

    /**
     * Format last active timestamp according to timeframe rules:
     * - Today (<24h): last seen {Xh Ym} ago / {Xm} ago / just now
     * - Yesterday: last seen yesterday at {g:i A}
     * - Within 1 week: last seen {X} days ago
     * - Over 1 week: last seen on d/m/Y
     *
     * // YB - 26-08-2026 code comment
     */
    protected function formatLastActiveHuman(): string
    {
        if (! $this->last_active_at) {
            return 'last seen recently';
        }

        $date = $this->last_active_at;
        $now = now();
        $diffMinutes = (int) $date->diffInMinutes($now);

        // 1. Within 24 hours (< 1440 mins): last seen {h}h {m}m ago
        if ($diffMinutes < 1440) {
            if ($diffMinutes < 1) {
                return 'last seen just now';
            }
            $hours = intdiv($diffMinutes, 60);
            $mins = $diffMinutes % 60;
            if ($hours > 0 && $mins > 0) {
                return "last seen {$hours}h {$mins}m ago";
            } elseif ($hours > 0) {
                return "last seen {$hours}h ago";
            }
            return "last seen {$mins}m ago";
        }

        // 2. Yesterday (24h to 48h ago or calendar yesterday)
        if ($date->isYesterday() || $diffMinutes < 2880) {
            return 'last seen yesterday at ' . $date->format('g:i A');
        }

        // 3. Within 1 week (2 to 7 days ago)
        $days = (int) $date->diffInDays($now);
        if ($days <= 7) {
            $dayCount = max(1, $days);
            $dayLabel = $dayCount === 1 ? '1 day' : "{$dayCount} days";
            return "last seen {$dayLabel} ago";
        }

        // 4. More than a week
        return 'last seen on ' . $date->format('d/m/Y');
    }
}
