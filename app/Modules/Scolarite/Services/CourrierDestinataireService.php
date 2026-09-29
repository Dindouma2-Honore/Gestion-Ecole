<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\CourrierDestinataireServiceContract;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\ParentTuteur;

class CourrierDestinataireService implements CourrierDestinataireServiceContract
{
    public function resoudreParents(string $cible, array $criteres = []): array
    {
        $query = ParentTuteur::query()->select('parents_tuteurs.*')->distinct();

        if ($cible === 'parents_selectionnes') {
            $query->whereKey($criteres['parent_ids'] ?? []);
        } elseif (in_array($cible, ['niveau', 'classe', 'classes', 'sous_niveaux'], true)) {
            $query->join('eleve_parent', 'eleve_parent.parent_id', '=', 'parents_tuteurs.id')
                ->join('inscriptions', 'inscriptions.eleve_id', '=', 'eleve_parent.eleve_id')
                ->join('classes', 'classes.id', '=', 'inscriptions.classe_id')
                ->whereNull('inscriptions.deleted_at')
                ->where('inscriptions.statut', 'active');

            if ($cible === 'niveau') {
                $query->whereIn('classes.niveau_id', $criteres['niveau_ids'] ?? []);
            } else {
                $query->whereIn('classes.id', $criteres['classe_ids'] ?? []);
            }
        } elseif ($cible !== 'tous_parents') {
            return [];
        }

        return $query->get()->map(fn (ParentTuteur $parent): array => [
            'type' => 'parent',
            'id' => $parent->id,
            'nom' => trim($parent->prenom.' '.$parent->nom),
            'email' => $parent->email,
            'telephone' => $parent->telephone,
        ])->all();
    }

    public function optionsParents(): array
    {
        return ParentTuteur::orderBy('nom')->get()->mapWithKeys(
            fn (ParentTuteur $parent): array => [$parent->id => trim($parent->prenom.' '.$parent->nom)]
        )->all();
    }

    public function optionsClasses(): array
    {
        return Classe::orderBy('nom')->pluck('nom', 'id')->all();
    }
}
