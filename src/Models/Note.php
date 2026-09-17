<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
        'mentioned_user_ids',
    ];

    protected $casts = [
        'mentioned_user_ids' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    protected static function newFactory(): NoteFactory
    {
        return NoteFactory::new();
    }
}
