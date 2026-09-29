<?php

declare(strict_types=1);

namespace App\Modules\RH\Services;

use App\Modules\RH\Models\BulletinPaie;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class BulletinPaiePdfService
{
    public function mefientPdf(BulletinPaie $bulletin): \Barryvdh\DomPDF\PDF
    {
        $bulletin->loadMissing(['employe', 'contrat', 'lignes']);

        return Pdf::loadView('rh::pdf.bulletin-paie', [
            'bulletin' => $bulletin,
        ])->setPaper('a4');
    }

    public function telecharger(BulletinPaie $bulletin): Response
    {
        $pdf = $this->mefientPdf($bulletin);
        $nomFichier = "bulletin-paie-{$bulletin->employe?->matricule}-{$bulletin->mois}-{$bulletin->annee}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'"',
        ]);
    }

    public function imprimer(BulletinPaie $bulletin): Response
    {
        $pdf = $this->mefientPdf($bulletin);
        $nomFichier = "bulletin-paie-{$bulletin->employe?->matricule}-{$bulletin->mois}-{$bulletin->annee}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nomFichier.'"',
        ]);
    }

    public function imprimerTous(int $mois, int $annee): Response
    {
        $bulletins = BulletinPaie::query()
            ->with(['employe', 'contrat', 'lignes'])
            ->where('mois', $mois)
            ->where('annee', $annee)
            ->orderBy('employe_id')
            ->get();

        abort_if($bulletins->isEmpty(), 404, 'Aucun bulletin trouvé pour cette période.');

        $pdf = Pdf::loadView('rh::pdf.bulletin-paie', [
            'bulletins' => $bulletins,
            'bulletin' => $bulletins->first(),
        ])->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="bulletins-paie-'.$mois.'-'.$annee.'.pdf"',
        ]);
    }
}
