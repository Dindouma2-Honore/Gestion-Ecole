<?php

namespace App\Mail;

use App\Modules\Socle\Models\TacheValidation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TacheValidationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TacheValidation $validation
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->validation->validateur?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Nouvelle validation requise pour la tâche : '.$this->validation->tache->titre,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tache-validation',
        );
    }
}
