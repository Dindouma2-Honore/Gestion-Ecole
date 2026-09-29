<?php

declare(strict_types=1);

namespace App\Modules\Finances\Mail;

use App\Modules\Finances\Models\FacturePreinscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InscriptionConfirmeeMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{nom:string, contenu:string}|null $recu */
    public function __construct(public FacturePreinscription $facture, private ?array $recu = null) {}

    public function build(): self
    {
        $mail = $this->subject("Inscription confirmée — {$this->facture->eleve_nom}")
            ->view('finances::mail.inscription-confirmee');

        if ($this->recu !== null) {
            $mail->attachData($this->recu['contenu'], $this->recu['nom'], ['mime' => 'application/pdf']);
        }

        return $mail;
    }
}
