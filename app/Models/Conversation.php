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
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
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
            ->withPivot(['role', 'last_read_at'])
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
     * Check if a specific user is a participant of this conversation.
     *
     * // YB - 24-08-2026 code comment
     */
    public function isParticipant(int $userId): bool
    {
        return $this->participants()->where('users.id', $userId)->exists();
    }
}
