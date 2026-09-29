<?php

namespace App\Mail;

use App\Modules\RH\Models\Inspection;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InspectionPedagogiqueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Inspection $inspection
    ) {}

    public function envelope(): Envelope
    {
        $email = $this->inspection->enseignant?->employe?->email ?? $this->inspection->enseignant?->employe?->user?->email;

        return new Envelope(
            to: array_filter([$email]),
            subject: 'Nouvelle Inspection Pédagogique enregistrée - Ambassadors Complex',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inspection-pedagogique',
        );
    }
}
