<?php

namespace Easyreply\Inbox\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConversationReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $bodyText,
        public readonly string $subjectLine,
    ) {}

    public function build(): static
    {
        return $this->subject($this->subjectLine)
            ->html(nl2br(e($this->bodyText)));
    }
}
