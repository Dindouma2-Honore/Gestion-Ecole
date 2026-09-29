<?php

declare(strict_types=1);

namespace App\Modules\Assiduite\Services;

use App\Modules\Assiduite\Contracts\VisiteurServiceContract;
use App\Modules\Assiduite\Models\Visiteur;
use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisiteurService implements VisiteurServiceContract
{
    public function __construct(
        private ?ParentTuteurServiceInterface $parentService = null
    ) {}

    public function enregistrerEntree(string $nom, string $motif, ?Model $personneVisitee, ?string $telephone = null): object
    {
        return Visiteur::create([
            'nom' => $nom,
            'telephone' => $telephone,
            'motif' => $motif,
            'personne_visitee_type' => $personneVisitee ? get_class($personneVisitee) : null,
            'personne_visitee_id' => $personneVisitee?->getKey(),
            'heure_entree' => now(),
            'enregistre_par' => Auth::id() ?? 1,
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
        $personnesAutorisees = DB::table('eleve_parent')
            ->join('parents_tuteurs', 'eleve_parent.parent_id', '=', 'parents_tuteurs.id')
            ->where('eleve_parent.eleve_id', $eleveId)
            ->where('eleve_parent.autorise_recuperation', true)
            ->get(['parents_tuteurs.nom', 'parents_tuteurs.prenom']);

        return $personnesAutorisees->contains(function ($personne) use ($nomVisiteur) {
            $nomComplet = strtolower(trim(($personne->nom ?? '') . ' ' . ($personne->prenom ?? '')));
            return str_contains($nomComplet, strtolower(trim($nomVisiteur)));
        });
    }
}
