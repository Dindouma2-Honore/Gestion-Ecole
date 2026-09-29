<?php

namespace App\Mail;

use App\Models\User;
use App\Modules\Socle\Models\Tache;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TacheValideeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tache $tache,
        public User $responsable
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Validation de la tâche : {$this->tache->titre}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tache-validee',
        );
    }
}
