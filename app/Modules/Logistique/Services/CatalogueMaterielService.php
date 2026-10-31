<?php

declare(strict_types=1);

namespace App\Modules\Logistique\Services;

use App\Modules\Logistique\Contracts\CatalogueMaterielInterface;
use App\Modules\Logistique\Enums\NatureMateriel;
use App\Modules\Logistique\Exceptions\CategorieMaterielChampsInvalidesException;
use App\Modules\Logistique\Exceptions\CategorieMaterielCodeDejaUtiliseException;
use App\Modules\Logistique\Exceptions\CategorieMaterielInactiveException;
use App\Modules\Logistique\Exceptions\CategorieMaterielInconnueException;
use App\Modules\Logistique\Exceptions\CategorieMaterielUtiliseeException;
use App\Modules\Logistique\Exceptions\NatureMaterielInvalideException;
use App\Modules\Logistique\Models\CategorieMateriel;
use App\Modules\Logistique\Models\Equipement;

class CatalogueMaterielService implements CatalogueMaterielInterface
{
    public function lister(bool $inclusInactives = false): array
    {
        return CategorieMateriel::query()
            ->when(! $inclusInactives, fn ($query) => $query->where('actif', true))
            ->orderBy('ordre')
            ->orderBy('nom')
            ->get()
            ->map(fn (CategorieMateriel $categorie): array => [
                'id' => (int) $categorie->id,
                'code' => (string) $categorie->code,
                'nom' => (string) $categorie->nom,
                'nature' => $categorie->nature->value,
                'actif' => (bool) $categorie->actif,
                'ordre' => (int) $categorie->ordre,
            ])
            ->all();
    }

    public function creer(string $code, string $nom, string $nature, int $ordre = 0): int
    {
        $identite = $this->validerIdentite($code, $nom, $nature, null);

        $categorie = CategorieMateriel::query()->create([
            'code' => $identite['code'],
            'nom' => $identite['nom'],
            'nature' => $identite['nature']->value,
            'actif' => true,
            'ordre' => max(0, $ordre),
        ]);

        return (int) $categorie->id;
    }

    public function modifier(int $categorieId, string $code, string $nom, string $nature, int $ordre): void
    {
        $categorie = $this->categorie($categorieId);
        $identite = $this->validerIdentite($code, $nom, $nature, $categorieId);

        $categorie->update([
            'code' => $identite['code'],
            'nom' => $identite['nom'],
            'nature' => $identite['nature']->value,
            'ordre' => max(0, $ordre),
        ]);
    }

    public function activer(int $categorieId): void
    {
        $this->categorie($categorieId)->update(['actif' => true]);
    }

    public function desactiver(int $categorieId): void
    {
        $this->categorie($categorieId)->update(['actif' => false]);
    }

    public function supprimer(int $categorieId): void
    {
        $categorie = $this->categorie($categorieId);

        $nbEquipements = Equipement::query()->where('categorie_id', $categorie->id)->count();

        if ($nbEquipements > 0) {
            throw new CategorieMaterielUtiliseeException($categorieId, $nbEquipements);
        }

        $categorie->delete();
    }

    public function nature(int $categorieId): string
    {
        return $this->categorie($categorieId)->nature->value;
    }

    public function validerAffectation(int $categorieId): void
    {
        $categorie = $this->categorie($categorieId);

        if (! $categorie->actif) {
            throw new CategorieMaterielInactiveException($categorieId);
        }
    }

    private function categorie(int $categorieId): CategorieMateriel
    {
        return CategorieMateriel::query()->find($categorieId)
            ?? throw new CategorieMaterielInconnueException($categorieId);
    }

    /**
     * @return array{code:string, nom:string, nature:NatureMateriel}
     */
    private function validerIdentite(string $code, string $nom, string $nature, ?int $categorieId): array
    {
        $code = trim($code);
        $nom = trim($nom);

        if ($code === '') {
            throw new CategorieMaterielChampsInvalidesException('code');
        }

        if ($nom === '') {
            throw new CategorieMaterielChampsInvalidesException('nom');
        }

        $natureValide = NatureMateriel::tryFrom(mb_strtolower(trim($nature)));

        if ($natureValide === null) {
            throw new NatureMaterielInvalideException($nature);
        }

        $doublon = CategorieMateriel::query()
            ->where('code', $code)
            ->when($categorieId !== null, fn ($query) => $query->whereKeyNot($categorieId))
            ->exists();

        if ($doublon) {
            throw new CategorieMaterielCodeDejaUtiliseException($code);
        }

        return ['code' => $code, 'nom' => $nom, 'nature' => $natureValide];
    }
}
