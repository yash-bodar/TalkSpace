<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'avatar_path', 'last_active_at', 'status_text', 'status_emoji', 'status_expires_at', 'web_push_subscription'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * // YB - 14-09-2026 code comment
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'status_expires_at' => 'datetime',
            'web_push_subscription' => 'array',
            'password' => 'hashed',
        ];
    }

    /**
     * Conversations the user is a participant of.
     *
     * // YB - 24-08-2026 code comment
     */
    public function conversations(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_users')
            ->withPivot([
                'role',
                'last_read_at',
                'last_delivered_at',
                'is_pinned',
                'pinned_at',
                'is_archived',
                'archived_at',
                'is_muted',
                'muted_until',
            ])
            ->withTimestamps();
    }

    /**
     * Messages sent by the user.
     *
     * // YB - 24-08-2026 code comment
     */
    public function messages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    /**
     * Avatar URL attribute generator.
     *
     * // YB - 25-08-2026 avatar generator with brand magenta fallback
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=D91A8D&color=fff&bold=true';
    }
}

