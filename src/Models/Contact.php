<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'display_name',
    ];

    public function channelIdentities(): HasMany
    {
        return $this->hasMany(ContactChannelIdentity::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    protected static function newFactory(): ContactFactory
    {
        return ContactFactory::new();
    }
}
