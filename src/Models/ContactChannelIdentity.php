<?php

namespace Easyreply\Inbox\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactChannelIdentity extends Model
{
    protected $fillable = [
        'contact_id',
        'channel_type',
        'external_id',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
