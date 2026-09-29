<?php

namespace App\Mail;

use App\Modules\RH\Models\Pointage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PointageConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Pointage $pointage
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation de Pointage & Assiduité - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pointage-confirmation',
        );
    }
}
