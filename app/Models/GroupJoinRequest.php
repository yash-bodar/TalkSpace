<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupJoinRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'status',
    ];

    /**
     * Group conversation for this request.
     *
     * // YB - 26-08-2026 code comment
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * User who requested to join.
     *
     * // YB - 26-08-2026 code comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
