<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Services;

use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\VieScolaire\Contracts\ReclamationServiceInterface;
use App\Modules\VieScolaire\Models\Reclamation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ReclamationService implements ReclamationServiceInterface
{
    public function __construct(
        private readonly TacheServiceContract $tache,
    ) {}

    public function signaler(string $type, string $description, ?Model $signalePar, string $priorite = 'normale'): object
    {
        $delai = match ($priorite) {
            'urgente' => now()->addDay(),
            'haute' => now()->addDays(3),
            default => now()->addDays(7),
        };

        return Reclamation::create([
            'type' => $type,
            'description' => $description,
            'priorite' => $priorite,
            'signale_par_type' => $signalePar ? get_class($signalePar) : null,
            'signale_par_id' => $signalePar?->id,
            'delai_reponse' => $delai,
            'statut' => 'ouverte',
            'created_at' => now(),
        ]);
    }

    public function affecter(int $reclamationId, int $responsableId): void
    {
        $reclamation = Reclamation::findOrFail($reclamationId);
        $reclamation->update(['responsable_id' => $responsableId]);
        $reclamation->changerStatut('affectee');

        // Génère une Tâche de suivi — même principe que A.8, D.37, E.46.
        $tacheCreee = $this->tache->creerTache(
            titre: "Traiter la réclamation #{$reclamationId}",
            responsableId: $responsableId,
            echeance: $reclamation->delai_reponse,
            taskable: $reclamation
        );

        $reclamation->update(['tache_id' => $tacheCreee->id]);
    }

    public function repondre(int $reclamationId, string $reponse): void
    {
        $reclamation = Reclamation::findOrFail($reclamationId);
        $reclamation->update(['reponse' => $reponse]);
        $reclamation->changerStatut('resolue');
    }

    public function cloturer(int $reclamationId): void
    {
        Reclamation::findOrFail($reclamationId)->changerStatut('cloturee');
    }

    public function getReclamationsEnRetard(): Collection
    {
        return Reclamation::where('delai_reponse', '<', now())
            ->whereNotIn('statut', ['resolue', 'cloturee'])
            ->get();
    }

    public function getStatistiquesParCategorie(): Collection
    {
        return Reclamation::selectRaw('categorie, count(*) as total')
            ->groupBy('categorie')
            ->get();
    }
}
