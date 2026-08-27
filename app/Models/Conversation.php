<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'title',
        'description',
        'avatar_path',
        'is_public',
        'invite_code',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * Users who are participants in this conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_users')
            ->withPivot(['role', 'last_read_at', 'last_delivered_at'])
            ->withTimestamps();
    }

    /**
     * Pivot records for participants.
     *
     * // YB - 24-08-2026 code comment
     */
    public function conversationUsers(): HasMany
    {
        return $this->hasMany(ConversationUser::class);
    }

    /**
     * Pending or past join requests for this group.
     *
     * // YB - 26-08-2026 code comment
     */
    public function joinRequests(): HasMany
    {
        return $this->hasMany(GroupJoinRequest::class);
    }

    /**
     * All messages in this conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Latest message in this conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Get the other participant in a direct 1-1 conversation.
     *
     * // YB - 25-08-2026 code comment
     */
    public function getDirectRecipient(?int $authUserId = null): ?User
    {
        if ($this->type !== 'direct') {
            return null;
        }

        $userId = $authUserId ?? auth()->id();
        if (! $userId) {
            return $this->participants->first();
        }

        return $this->participants->first(fn ($p) => (int) $p->id !== (int) $userId);
    }

    /**
     * Check if a specific user is an admin of this conversation.
     *
     * // YB - 26-08-2026 code comment
     */
    public function isAdmin(int $userId): bool
    {
        if ($this->type !== 'group') {
            return false;
        }

        if ($this->relationLoaded('conversationUsers') && $this->conversationUsers) {
            $pivot = $this->conversationUsers->firstWhere('user_id', $userId);
            return $pivot?->role === 'admin';
        }

        return $this->conversationUsers()->where('user_id', $userId)->where('role', 'admin')->exists();
    }

    /**
     * Get the role of a specific user in this conversation ('admin' | 'member' | null).
     *
     * // YB - 26-08-2026 code comment
     */
    public function getUserRole(int $userId): ?string
    {
        if ($this->relationLoaded('conversationUsers') && $this->conversationUsers) {
            $pivot = $this->conversationUsers->firstWhere('user_id', $userId);
            return $pivot?->role;
        }

        if ($this->relationLoaded('participants') && $this->participants) {
            $participant = $this->participants->firstWhere('id', $userId);
            return $participant?->pivot?->role;
        }

        $pivot = $this->conversationUsers()->where('user_id', $userId)->first(['role']);
        return $pivot?->role;
    }

    /**
     * Check if a specific user is a participant of this conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function isParticipant(int $userId): bool
    {
        return $this->participants()->where('users.id', $userId)->exists();
    }

    /**
     * Get avatar URL for conversation.
     *
     * // YB - 26-08-2026 code comment
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path);
        }

        $name = $this->title ?? 'Group Chat';
        return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=D91A8D&color=fff&bold=true';
    }

    /**
     * Generate a unique 16-character invite code.
     *
     * // YB - 26-08-2026 code comment
     */
    public static function generateUniqueInviteCode(): string
    {
        do {
            $code = \Illuminate\Support\Str::random(16);
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }
}
