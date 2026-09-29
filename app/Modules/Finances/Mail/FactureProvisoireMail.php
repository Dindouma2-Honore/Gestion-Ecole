<?php

declare(strict_types=1);

namespace App\Modules\Finances\Mail;

use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FactureProvisoireMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FacturePreinscription $facture, private ?string $pdf = null) {}

    public function build(): self
    {
        $annee = app(AnneeScolaireServiceContract::class)
            ->getAnneeScolaire($this->facture->annee_scolaire_id);
        $pdf = $this->pdf ?? Pdf::loadView(
            'finances::pdf.facture-provisoire',
            ['facture' => $this->facture->loadMissing('lignes'), 'annee' => $annee],
        )->setPaper('a4')->output();

        return $this->subject("Facture provisoire {$this->facture->reference}")
            ->view('finances::mail.facture-provisoire')
            ->attachData($pdf, "facture-provisoire-{$this->facture->reference}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }
}
