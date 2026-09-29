<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Contracts\MaintenanceServiceInterface;
use App\Modules\Logistique\Models\Equipement;
use App\Modules\Logistique\Models\EquipementHistoriqueLocalisation;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Collection;

class EquipementService implements EquipementServiceInterface
{
    public function __construct(
        private readonly MaintenanceServiceInterface $maintenance,
        private readonly AuditServiceContract $audit,
    ) {}

    public function enregistrerEquipement(array $donnees): object
    {
        return Equipement::create($donnees);
    }

    public function deplacer(int $equipementId, int $nouvelleSalleId): void
    {
        $equipement = Equipement::findOrFail($equipementId);
        $ancienneSalle = $equipement->salle_id;

        $equipement->update(['salle_id' => $nouvelleSalleId]);

        EquipementHistoriqueLocalisation::create([
            'equipement_id' => $equipementId,
            'ancienne_salle_id' => $ancienneSalle,
            'nouvelle_salle_id' => $nouvelleSalleId,
            'date_deplacement' => now(),
        ]);
    }

    public function declarerPanne(int $equipementId, string $description): object
{
    $equipement = Equipement::findOrFail($equipementId);
    $equipement->update(['etat' => 'a_reparer']);

    // Génère automatiquement une demande de maintenance — même principe
    // de délégation déjà vu plusieurs fois dans le projet.
    return $this->maintenance->signalerPanne($equipementId, $description);
    //                                        ^^^^^^^^^^^^^ id, plus l'objet $equipement
}

    public function mettreAuRebut(int $equipementId, string $motif): void
    {
        $equipement = Equipement::findOrFail($equipementId);
        $equipement->update(['etat' => 'mis_au_rebut', 'date_mise_au_rebut' => now()]);

        $this->audit->enregistrerAvecMotif($equipement, "Mise au rebut de l'équipement #{$equipementId}", $motif);
    }

    public function getValeurPatrimoine(): float
    {
        return (float) Equipement::where('etat', '!=', 'mis_au_rebut')->sum('valeur_acquisition');
    }

    public function getEquipementsSousGarantie(): Collection
    {
        return Equipement::whereNotNull('garantie_fin')
            ->where('garantie_fin', '>=', now())
            ->get();
    }

    // declarerPanne() délègue directement à MaintenanceServiceInterface::
    // signalerPanne() — c'est le pattern de délégation déjà vu de nombreuses
    // fois, appliqué une fois de plus ici. Ce module ne gère pas lui-même
    // le processus de réparation, seulement l'état de l'équipement.
}
