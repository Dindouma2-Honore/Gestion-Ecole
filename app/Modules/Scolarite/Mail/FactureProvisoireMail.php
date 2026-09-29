<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Mail;

use App\Modules\Scolarite\Models\FactureGeneree;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Facture provisoire envoyée automatiquement par e-mail à la création de
 * l'inscription (Module 5 §1). Reste volontairement un e-mail simple —
 * l'inscription ne doit jamais échouer à cause d'un problème d'envoi, voir
 * FactureService::genererProvisoire().
 */
class FactureProvisoireMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly FactureGeneree $facture) {}

    public function build(): self
    {
        return $this
            ->subject("Facture provisoire {$this->facture->numero} — en attente de versement")
            ->view('scolarite::filament.mail.facture-provisoire', ['facture' => $this->facture->loadMissing(['inscription.eleve', 'inscription.classe'])]);
    }
}
