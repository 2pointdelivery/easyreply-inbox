<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\VoiceAgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceAgent extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'driver',
        'prompt',
        'voice_id',
        'business_hours',
        'transfer_target_e164',
        'is_active',
    ];

    protected $casts = [
        'business_hours' => 'array',
        'is_active' => 'boolean',
    ];

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    protected static function newFactory(): VoiceAgentFactory
    {
        return VoiceAgentFactory::new();
    }
}
