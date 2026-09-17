<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    protected $fillable = [
        'conversation_id',
        'direction',
        'channel',
        'external_id',
        'in_reply_to_external_id',
        'sender_type',
        'sender_id',
        'body',
        'raw_payload',
        'ai_generated',
        'status',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'ai_generated' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    protected static function newFactory(): MessageFactory
    {
        return MessageFactory::new();
    }
}
