<?php

declare(strict_types=1);

namespace App\Modules\VieScolaire\Services;

use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use App\Modules\VieScolaire\Contracts\VisiteurServiceInterface;
use App\Modules\VieScolaire\Models\Visiteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class VisiteurService implements VisiteurServiceInterface
{
    public function __construct(
        private readonly ParentTuteurServiceInterface $parent,
    ) {}

    public function enregistrerEntree(string $nom, string $motif, ?Model $personneVisitee, ?string $telephone = null): object
    {
        return Visiteur::create([
            'nom' => $nom,
            'telephone' => $telephone,
            'motif' => $motif,
            'personne_visitee_type' => $personneVisitee ? get_class($personneVisitee) : null,
            'personne_visitee_id' => $personneVisitee?->id,
            'heure_entree' => now(),
            'enregistre_par' => Auth::id(),
        ]);
    }

    public function enregistrerSortie(int $visiteurId): void
    {
        Visiteur::where('id', $visiteurId)->update(['heure_sortie' => now()]);
    }

    public function getVisiteursPresents(): Collection
    {
        return Visiteur::whereNull('heure_sortie')->get();
    }

    public function verifierAutorisationRecuperationEleve(string $nomVisiteur, int $eleveId): bool
    {
        // Réutilise directement le contrat Scolarité déjà exposé pour G.56 —
        // pas de duplication de la donnée d'autorisation.
      $personnesAutorisees = collect($this->parent->getPersonnesAutoriseesRecuperer($eleveId));
    $nomVisiteurNormalise = strtolower(trim($nomVisiteur));

    return $personnesAutorisees->contains(function (array $p) use ($nomVisiteurNormalise) {
        $nomPrenom = strtolower("{$p['nom']} {$p['prenom']}");
        $prenomNom = strtolower("{$p['prenom']} {$p['nom']}");

        return $nomVisiteurNormalise === $nomPrenom || $nomVisiteurNormalise === $prenomNom;
    });
        // Note : comparaison par nom fragile (homonymes possibles) — en
        // production, préférer une vérification par pièce d'identité ou
        // par badge parent plutôt que le nom seul. À discuter avec Joel.
    }
}
