<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\InboxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inbox extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'channel_type',
        'name',
        'config',
        'is_active',
    ];

    protected $casts = [
        'config' => 'encrypted:array',
        'is_active' => 'boolean',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    protected static function newFactory(): InboxFactory
    {
        return InboxFactory::new();
    }
}
