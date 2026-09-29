<?php

namespace App\Mail;

use App\Models\User;
use App\Modules\Socle\Models\Reunion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReunionOrganiseeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reunion $reunion,
        public User $participant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invitation à la réunion : {$this->reunion->titre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reunion-organisee',
        );
    }
}
