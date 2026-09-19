<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\CallLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallLog extends Model
{
    use BelongsToTeam;
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RINGING = 'ringing';

    public const STATUS_IN_PROGRESS = 'in-progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NO_ANSWER = 'no-answer';

    public const STATUS_BUSY = 'busy';

    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'team_id',
        'voice_agent_id',
        'inbox_id',
        'conversation_id',
        'contact_id',
        'direction',
        'from_e164',
        'to_e164',
        'provider',
        'provider_call_id',
        'status',
        'started_at',
        'ended_at',
        'duration_seconds',
        'transcript',
        'summary',
        'sentiment',
        'priority_suggestion',
        'post_actions',
        'transfer_outcome',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'post_actions' => 'array',
    ];

    public function voiceAgent(): BelongsTo
    {
        return $this->belongsTo(VoiceAgent::class);
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(Inbox::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CallAction::class);
    }

    protected static function newFactory(): CallLogFactory
    {
        return CallLogFactory::new();
    }
}
