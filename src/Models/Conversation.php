<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Concerns\BelongsToTeam;
use Easyreply\Inbox\Database\Factories\ConversationFactory;
use Easyreply\Inbox\Support\SlaCalculator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTeam;
    use HasFactory;

    public static function booted(): void
    {
        // Centralized here (rather than in each creation path) so every
        // conversation — from any channel, or created directly — gets an
        // sla_due_at from the team's matching SlaPolicy, if one exists.
        // ConversationController::update separately recomputes this when
        // priority changes after creation.
        static::creating(function (Conversation $conversation) {
            if ($conversation->sla_due_at || ! $conversation->team_id) {
                return;
            }

            $conversation->sla_due_at = app(SlaCalculator::class)
                ->dueAt($conversation->team_id, $conversation->priority ?: 'normal');
        });
    }

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

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class)->orderBy('created_at');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'conversation_label');
    }

    protected static function newFactory(): ConversationFactory
    {
        return ConversationFactory::new();
    }
}
