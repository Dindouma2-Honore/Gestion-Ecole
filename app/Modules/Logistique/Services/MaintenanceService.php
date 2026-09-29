<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\MaintenanceServiceInterface;
use App\Modules\Logistique\Models\DemandeIntervention;
use App\Modules\Logistique\Models\Equipement;
use App\Modules\Logistique\Models\PlanMaintenancePreventive;
use Illuminate\Support\Collection;

class MaintenanceService implements MaintenanceServiceInterface
{
    public function __construct(
        // TODO: brancher DepenseServiceContract une fois le namespace réel
        // du module Finances (E.44) confirmé avec Joel — même point resté
        // ouvert que dans SanteService (module VieScolaire) pour la
        // Notification. En attendant, terminerReparation() ne déclenche pas
        // encore réellement la demande de dépense, voir commentaire ci-dessous.
    ) {}

    public function signalerPanne(int $equipementId, string $description): object
{
    return DemandeIntervention::create([
        'equipement_id' => $equipementId,
        'description' => $description,
        'type' => 'curative',
        'statut' => 'signalee',
        'date_signalement' => now(),
    ]);
}

    public function diagnostiquer(int $demandeId, string $diagnostic, float $coutEstime): void
    {
        DemandeIntervention::where('id', $demandeId)->update([
            'diagnostic' => $diagnostic,
            'cout' => $coutEstime,
            'statut' => 'diagnostiquee',
        ]);
    }

    public function terminerReparation(int $demandeId, float $coutReel, ?string $piecesUtilisees = null): void
    {
        $demande = DemandeIntervention::findOrFail($demandeId);
        $demande->update([
            'cout' => $coutReel,
            'pieces_utilisees' => $piecesUtilisees,
            'statut' => 'terminee',
            'date_reparation' => now(),
        ]);

        // Remet l'équipement en bon état — referme la boucle avec H.60.
        if ($demande->equipement_id) {
            Equipement::where('id', $demande->equipement_id)->update(['etat' => 'bon']);
        }

        // Si le coût de réparation dépasse un certain seuil, ça devient une
        // Dépense (module Finances, E.44) — même principe de délégation
        // qu'ailleurs dans le projet (à confirmer avec Joel : intégration
        // systématique ou seulement au-delà d'un montant significatif, et
        // namespace exact du contrat DepenseServiceContract à brancher ici
        // une fois le module Finances codé).
        //
        // if ($coutReel > 0) {
        //     app(\App\Modules\Finances\Contracts\DepenseServiceContract::class)->demanderDepense([
        //         'categorie_id' => null, // catégorie "maintenance" à définir
        //         'beneficiaire' => $demande->technicien_externe_nom ?? 'Technicien interne',
        //         'montant' => $coutReel,
        //         'mode_paiement' => 'especes',
        //     ]);
        // }
    }

    public function planifierMaintenancePreventive(int $equipementId, int $frequenceJours): object
    {
        return PlanMaintenancePreventive::create([
            'equipement_id' => $equipementId,
            'frequence_jours' => $frequenceJours,
            'prochaine_echeance' => now()->addDays($frequenceJours),
        ]);
    }

    public function getMaintenancesDues(): Collection
    {
        return PlanMaintenancePreventive::where('prochaine_echeance', '<=', now())->get();
    }

    // Job planifié (à créer séparément dans app/Console) :
    // $schedule->command('maintenance:verifier-echeances')->daily();
    // (notifie le Chargé de logistique pour chaque maintenance préventive
    // due, via le module Notifications — implémentation similaire aux
    // nombreux Jobs déjà vus ailleurs dans le projet.)
}
