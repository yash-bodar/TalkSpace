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
            : $this->avatar_url;

        // Get user's last_read_at timestamp from pivot or relation
        $lastReadAt = null;
        if ($this->relationLoaded('conversationUsers') && $this->conversationUsers) {
            $userPivot = $this->conversationUsers->firstWhere('user_id', $authUserId);
            $lastReadAt = $userPivot?->last_read_at;
        } elseif ($this->relationLoaded('participants') && $this->participants) {
            $currentParticipant = $this->participants->firstWhere('id', $authUserId);
            $lastReadAt = $currentParticipant?->pivot?->last_read_at;
        }

        // Calculate unread message count efficiently
        $unreadCount = (int) ($this->unread_count ?? 0);
        if (! isset($this->unread_count) && $this->relationLoaded('messages') && $this->messages) {
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

        // YB - 08-09-2026 - If the latest message was deleted for me only, show the latest visible message before it. If deleted for everyone, keep showing deleted for everyone.
        if ($latestMsg && $authUserId && ! $latestMsg->is_deleted_for_everyone) {
            $deletedFor = $latestMsg->deleted_for_user_ids ?? [];
            if (is_array($deletedFor) && in_array($authUserId, $deletedFor)) {
                if ($this->relationLoaded('messages') && $this->messages && $this->messages->isNotEmpty()) {
                    $latestMsg = $this->messages
                        ->filter(function ($m) use ($authUserId) {
                            if ($m->is_deleted_for_everyone) {
                                return true;
                            }
                            $df = $m->deleted_for_user_ids ?? [];
                            return ! (is_array($df) && in_array($authUserId, $df));
                        })
                        ->last();
                } else {
                    $latestMsg = $this->messages()
                        ->where(function ($q) use ($authUserId) {
                            $q->where('is_deleted_for_everyone', true)
                                ->orWhere(function ($sub) use ($authUserId) {
                                    $sub->whereNull('deleted_for_user_ids')
                                        ->orWhereJsonDoesntContain('deleted_for_user_ids', $authUserId);
                                });
                        })
                        ->latest('created_at')
                        ->first();
                }
                $latestMsg?->loadMissing('sender');
            }
        }

        $participantsData = [];
        if ($this->relationLoaded('participants') && $this->participants) {
            $participantsData = $this->participants->map(function ($p) use ($request) {
                $userRes = (new UserResource($p))->resolve($request);
                $userRes['role'] = $p->pivot?->role ?? 'member';

                $lastRead = $p->pivot?->last_read_at;
                $userRes['last_read_at'] = $lastRead ? ($lastRead instanceof \Carbon\CarbonInterface ? $lastRead->toISOString() : \Illuminate\Support\Carbon::parse($lastRead)->toISOString()) : null;

                $lastDelivered = $p->pivot?->last_delivered_at;
                $userRes['last_delivered_at'] = $lastDelivered ? ($lastDelivered instanceof \Carbon\CarbonInterface ? $lastDelivered->toISOString() : \Illuminate\Support\Carbon::parse($lastDelivered)->toISOString()) : null;

                return $userRes;
            })->values()->all();
        }

        // Pending join requests for group admins
        $joinRequests = [];
        if ($this->type === 'group' && $this->isAdmin($authUserId)) {
            $this->loadMissing(['joinRequests.user']);
            $joinRequests = $this->joinRequests->where('status', 'pending')->map(function ($r) use ($request) {
                return [
                    'id' => $r->id,
                    'user' => (new UserResource($r->user))->resolve($request),
                    'status' => $r->status,
                    'created_at' => $r->created_at?->toISOString(),
                ];
            })->values()->all();
        }

        $hasPendingJoinRequest = false;
        if ($this->type === 'group' && $authUserId) {
            if ($this->relationLoaded('joinRequests') && $this->joinRequests) {
                $hasPendingJoinRequest = $this->joinRequests->where('user_id', $authUserId)->where('status', 'pending')->isNotEmpty();
            } else {
                $hasPendingJoinRequest = $this->joinRequests()->where('user_id', $authUserId)->where('status', 'pending')->exists();
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $title,
            'description' => $this->description,
            'avatar_url' => $avatar,
            'is_public' => (bool) $this->is_public,
            'invite_code' => $this->invite_code,
            'invite_url' => $this->invite_code ? route('chat.join', $this->invite_code) : null,
            'is_admin' => $this->isAdmin($authUserId),
            'user_role' => $this->getUserRole($authUserId),
            'is_member' => $this->isParticipant($authUserId),
            'has_pending_join_request' => $hasPendingJoinRequest,
            'join_requests' => $joinRequests,
            // YB - 26-08-2026 Resolve nested resources directly to avoid nested { data: ... } object wrapping
            'direct_recipient' => $directRecipient ? (new UserResource($directRecipient))->resolve($request) : null,
            'participants' => $participantsData,
            'latest_message' => $latestMsg ? (new MessageResource($latestMsg))->resolve($request) : null,
            'unread_count' => $unreadCount,
            'last_message_at' => $this->last_message_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
