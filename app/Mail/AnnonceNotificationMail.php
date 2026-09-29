<?php

namespace App\Mail;

use App\Modules\Communication\Models\Annonce;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnonceNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Annonce $annonce
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle Annonce : '.$this->annonce->titre,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.annonce-notification',
        );
    }
}
