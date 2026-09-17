<?php

namespace Easyreply\Inbox\Models;

use Easyreply\Inbox\Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    protected static function newFactory(): AttachmentFactory
    {
        return AttachmentFactory::new();
    }
}
