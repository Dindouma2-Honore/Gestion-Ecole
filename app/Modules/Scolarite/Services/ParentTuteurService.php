<?php

declare(strict_types=1);

namespace App\Modules\Scolarite\Services;

use App\Modules\Scolarite\Contracts\ParentTuteurServiceInterface;
use App\Modules\Scolarite\Exceptions\EleveIntrouvableException;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\ParentTuteur;

class ParentTuteurService implements ParentTuteurServiceInterface
{
    public function existe(int $parentId): bool
    {
        return ParentTuteur::whereKey($parentId)->exists();
    }

    public function getParent(int $parentId): array
    {
        $parent = ParentTuteur::findOrFail($parentId);

        return [
            'id' => $parent->id,
            'nom' => $parent->nom,
            'prenom' => $parent->prenom,
            'telephone' => $parent->telephone,
        ];
    }

    public function creer(array $donnees): array
    {
        $nom = trim((string) ($donnees['nom'] ?? ''));
        $prenom = trim((string) ($donnees['prenom'] ?? ''));
        $telephone = trim((string) ($donnees['telephone'] ?? ''));

        $existant = null;
        if ($nom !== '' && ! in_array(mb_strtolower($nom), ['tuteur à compléter', 'tuteur a completer', 'tuteur', 'parent', 'inconnu'], true)) {
            $existant = ParentTuteur::query()
                ->whereRaw('LOWER(TRIM(nom)) = ?', [mb_strtolower($nom)])
                ->when($prenom !== '', fn ($q) => $q->whereRaw('LOWER(TRIM(prenom)) = ?', [mb_strtolower($prenom)]))
                ->first();
        }

        if (! $existant && $telephone !== '') {
            $existant = ParentTuteur::where('telephone', $telephone)->first();
        }

        if ($existant) {
            $updates = [];
            if ($telephone !== '' && blank($existant->telephone)) {
                $updates['telephone'] = $telephone;
            }
            if (! empty($donnees['email']) && blank($existant->email)) {
                $updates['email'] = $donnees['email'];
            }
            if (! empty($donnees['profession']) && blank($existant->profession)) {
                $updates['profession'] = $donnees['profession'];
            }
            if (! empty($updates)) {
                $existant->update($updates);
            }

            return $existant->fresh()->toArray();
        }

        $parent = ParentTuteur::create([
            'nom' => $nom,
            'prenom' => $prenom,
            'profession' => $donnees['profession'] ?? null,
            'telephone' => $telephone ?: null,
            'email' => $donnees['email'] ?? null,
        ]);

        return $parent->toArray();
    }

    public function lierAEleve(int $parentId, int $eleveId, array $responsabilites): void
    {
        $eleve = Eleve::find($eleveId);

        if (! $eleve) {
            throw EleveIntrouvableException::pourId($eleveId);
        }

        $eleve->parentsTuteurs()->syncWithoutDetaching([
            $parentId => [
                'lien' => $responsabilites['lien'] ?? null,
                'responsable_legal' => $responsabilites['responsable_legal'] ?? false,
                'responsable_paiement' => $responsabilites['responsable_paiement'] ?? false,
                'autorise_recuperation' => $responsabilites['autorise_recuperation'] ?? false,
            ],
        ]);
    }
    public function getPersonnesAutoriseesRecuperer(int $eleveId): array
{
    $eleve = Eleve::find($eleveId);

    if (! $eleve) {
        throw EleveIntrouvableException::pourId($eleveId);
    }

    return $eleve->parentsTuteurs()
        ->wherePivot('autorise_recuperation', true)
        ->get()
        ->map(fn (ParentTuteur $p) => [
            'id' => $p->id,
            'nom' => $p->nom,
            'prenom' => $p->prenom,
            'telephone' => $p->telephone,
        ])
        ->all();
}
}
