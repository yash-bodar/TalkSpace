<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationUser extends Model
{
    use HasFactory;

    protected $table = 'conversation_users';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'last_read_at',
        'last_delivered_at',
        'is_pinned',
        'pinned_at',
        'is_archived',
        'archived_at',
        'is_muted',
        'muted_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * // YB - 14-09-2026 code comment
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'last_delivered_at' => 'datetime',
            'is_pinned' => 'boolean',
            'pinned_at' => 'datetime',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
            'is_muted' => 'boolean',
            'muted_until' => 'datetime',
        ];
    }

    /**
     * The conversation associated with this membership.
     *
     * // YB - 24-08-2026 code comment
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * The user associated with this membership.
     *
     * // YB - 24-08-2026 code comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
