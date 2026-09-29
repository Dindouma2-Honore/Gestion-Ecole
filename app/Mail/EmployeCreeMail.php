<?php

namespace App\Mail;

use App\Modules\RH\Models\Employe;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmployeCreeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employe $employe
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->employe->email ?? $this->employe->user?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Création de votre fiche personnel / employé - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.employe-cree',
        );
    }
}
