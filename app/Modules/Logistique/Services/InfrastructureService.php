<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\InfrastructureServiceInterface;
use App\Modules\Logistique\Models\Salle;
use App\Modules\Logistique\Models\TravauxInfrastructure;
use App\Modules\Socle\Contracts\AuditServiceContract;
use Illuminate\Support\Collection;

class InfrastructureService implements InfrastructureServiceInterface
{
    public function __construct(
        private readonly AuditServiceContract $audit,
    ) {}

    public function getSallesDisponibles(?int $niveauId = null, ?int $capaciteMin = null): Collection
    {
        return Salle::where('etat', '!=', 'hors_service')
            ->when($niveauId, fn ($q) => $q->where(fn ($q2) => $q2->where('niveau_id', $niveauId)->orWhereNull('niveau_id')))
            ->when($capaciteMin, fn ($q) => $q->where('capacite', '>=', $capaciteMin))
            ->get();
    }

    public function declarerHorsService(int $salleId, string $motif): void
    {
        $salle = Salle::findOrFail($salleId);
        $salle->update(['etat' => 'hors_service']);

        $this->audit->enregistrerAvecMotif($salle, "Salle #{$salleId} déclarée hors service", $motif);

        // Point important : si cette salle a des cours planifiés (D.26)
        // dans les jours à venir, il faudrait idéalement notifier ou
        // bloquer la planification — à discuter avec Joel/le développeur
        // pour décider si ce module doit interroger D.26 pour signaler
        // un conflit, ou si ça reste une action manuelle de la Direction.
    }

    public function planifierTravaux(int $salleId, string $description, \DateTimeInterface $dateDebut): object
    {
        return TravauxInfrastructure::create([
            'salle_id' => $salleId,
            'description' => $description,
            'date_debut' => $dateDebut,
            'statut' => 'planifie',
        ]);
    }

    public function terminerTravaux(int $travauxId): void
    {
        $travaux = TravauxInfrastructure::findOrFail($travauxId);
        $travaux->update(['date_fin_reelle' => now(), 'statut' => 'termine']);
        Salle::where('id', $travaux->salle_id)->update(['etat' => 'bon']);
    }

    // getSallesDisponibles() est LA méthode que le module D.26 (Emplois du
    // temps), déjà codé, devrait idéalement appeler pour lister les salles
    // proposées à la planification. Si D.26 interroge directement la table
    // `salles`, ce n'est pas bloquant, mais il serait plus propre de le
    // faire évoluer pour utiliser ce contrat une fois ce module disponible
    // — à signaler au développeur comme amélioration possible.
}
