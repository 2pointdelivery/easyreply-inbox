<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * The host app's user model, configured the same way Laravel's auth
     * guard resolves it (`auth.providers.users.model`) so this package never
     * hardcodes `App\Models\User`.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            config('auth.providers.users.model'),
            'team_user'
        )->withPivot('role')->withTimestamps();
    }

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }
}
