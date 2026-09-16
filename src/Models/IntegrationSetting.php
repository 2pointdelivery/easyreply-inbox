<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\IntegrationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IntegrationSetting extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'integration_key',
        'enabled',
        'config',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'encrypted:array',
    ];

    protected static function newFactory(): IntegrationSettingFactory
    {
        return IntegrationSettingFactory::new();
    }
}
