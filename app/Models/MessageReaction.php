<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'user_id',
        'emoji',
    ];

    /**
     * Associated message.
     *
     * // YB - 26-08-2026 code comment
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * User who reacted.
     *
     * // YB - 26-08-2026 code comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
