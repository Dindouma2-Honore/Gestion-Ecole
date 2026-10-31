<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Http\Controllers;

use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImprimerInscriptionDocumentController
{
    public function recu(Inscription $inscription, Request $request): Response
    {
        $paiementId = (int) $request->query('paiement');
        if ($paiementId > 0) {
            $recu = app(PaiementServiceContract::class)->getRecuPdf($paiementId);
        } else {
            $recu = app(InscriptionFacturationPort::class)->getRecuDernierVersement((int) $inscription->id);
        }
        abort_unless($recu, 404, 'Le reçu de versement est introuvable.');

        return response($recu['contenu'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($recu['nom'] ?? "recu-inscription-{$inscription->id}.pdf") . '"',
        ]);
    }

    public function facture(Inscription $inscription): mixed
    {
        $facture = FacturePreinscription::query()
            ->with(['lignes.groupe'])
            ->where('inscription_id', $inscription->id)
            ->latest('id')
            ->first();

        if ($facture) {
            try {
                $annee = app(AnneeScolaireServiceContract::class)->getAnneeScolaire((int) $facture->annee_scolaire_id);
            } catch (\Throwable) {
                $annee = null;
            }

            $pdf = Pdf::loadView('finances::pdf.facture-provisoire', compact('facture', 'annee'))
                ->setPaper('a4')
                ->output();

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="facture-provisoire-' . $facture->reference . '.pdf"',
            ]);
        }

        if ($inscription->factureDefinitive) {
            return redirect()->route('impressions.facture', ['facture' => $inscription->factureDefinitive->id]);
        }

        if ($inscription->factureProvisoire) {
            return redirect()->route('impressions.facture', ['facture' => $inscription->factureProvisoire->id]);
        }

        abort(404, 'Aucune facture trouvée pour cette inscription.');
    }
}
