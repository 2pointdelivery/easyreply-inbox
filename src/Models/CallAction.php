<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Database\Factories\CallActionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallAction extends Model
{
    use HasFactory;

    public const TYPE_CREATE_THREAD = 'create_thread';

    public const TYPE_TRIAGE = 'triage';

    public const TYPE_INTEGRATION = 'integration';

    public const TYPE_HANDOFF = 'handoff';

    public const TYPE_CALLBACK = 'callback';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'call_log_id',
        'action_type',
        'payload',
        'status',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class);
    }

    protected static function newFactory(): CallActionFactory
    {
        return CallActionFactory::new();
    }
}
