<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\McpConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class McpConnection extends Model
{
    use BelongsToTeam;
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'team_id',
        'app_slug',
        'composio_connection_id',
        'state',
        'scopes',
        'status',
    ];

    protected $casts = [
        'scopes' => 'array',
    ];

    protected $hidden = [
        'state',
    ];

    protected static function newFactory(): McpConnectionFactory
    {
        return McpConnectionFactory::new();
    }
}
