<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'type',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'read_at',
        'delivered_at',
        'is_edited',
        'edited_at',
        'is_deleted_for_everyone',
        'deleted_for_user_ids',
    ];

    protected function casts(): array
    {
        return [
            'attachment_size' => 'integer',
            'read_at' => 'datetime',
            'delivered_at' => 'datetime',
            'is_edited' => 'boolean',
            'edited_at' => 'datetime',
            'is_deleted_for_everyone' => 'boolean',
            'deleted_for_user_ids' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected $appends = [
        'attachment_url',
    ];

    /**
     * Parent conversation for this message.
     *
     * // YB - 24-08-2026 code comment
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Sender of this message.
     *
     * // YB - 24-08-2026 code comment
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get accessible attachment URL.
     *
     * // YB - 24-08-2026 code comment
     */
    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }
}
