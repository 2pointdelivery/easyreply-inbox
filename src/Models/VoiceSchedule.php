<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\VoiceScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoiceSchedule extends Model
{
    use BelongsToTeam;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'team_id',
        'to_e164',
        'scheduled_at',
        'recurrence',
        'status',
        'conversation_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    protected static function newFactory(): VoiceScheduleFactory
    {
        return VoiceScheduleFactory::new();
    }
}
