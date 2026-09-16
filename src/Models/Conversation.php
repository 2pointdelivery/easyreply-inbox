<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'team_id',
        'inbox_id',
        'contact_id',
        'assignee_id',
        'subject',
        'status',
        'priority',
        'sla_due_at',
        'first_response_at',
        'resolved_at',
    ];

    protected $casts = [
        'sla_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(Inbox::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'assignee_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }
}
