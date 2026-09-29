<?php

namespace App\Mail;

use App\Modules\Communication\Models\MessageParent;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MessageParentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MessageParent $messageParent
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Message aux Parents/Tuteurs : '.$this->messageParent->sujet,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message-parent',
        );
    }
}
