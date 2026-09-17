<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\SlaPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'priority',
        'first_response_minutes',
        'resolution_minutes',
    ];

    protected $casts = [
        'first_response_minutes' => 'integer',
        'resolution_minutes' => 'integer',
    ];

    protected static function newFactory(): SlaPolicyFactory
    {
        return SlaPolicyFactory::new();
    }
}
