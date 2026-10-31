<?php

use App\Models\User;
use App\Modules\Logistique\Contracts\CatalogueMaterielInterface;
use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Enums\NatureMateriel;
use App\Modules\Logistique\Exceptions\CategorieMaterielChampsInvalidesException;
use App\Modules\Logistique\Exceptions\CategorieMaterielCodeDejaUtiliseException;
use App\Modules\Logistique\Exceptions\CategorieMaterielInactiveException;
use App\Modules\Logistique\Exceptions\CategorieMaterielInconnueException;
use App\Modules\Logistique\Exceptions\CategorieMaterielUtiliseeException;
use App\Modules\Logistique\Exceptions\NatureMaterielInvalideException;
use App\Modules\Logistique\Filament\Resources\CategorieMaterielResource;
use App\Modules\Logistique\Filament\Resources\EquipementResource;
use App\Modules\Logistique\Models\CategorieMateriel;
use App\Modules\Logistique\Models\Equipement;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->catalogue ??= app(CatalogueMaterielInterface::class);
    $this->equipements ??= app(EquipementServiceInterface::class);
});

function donneesEquipementCatalogue(array $attributs = []): array
{
    return array_merge([
        'nom' => 'Équipement de test',
        'date_acquisition' => now()->toDateString(),
    ], $attributs);
}

it('crée une catégorie de matériel active', function () {
    $categorieId = $this->catalogue->creer('MAT-PC', 'Ordinateurs', 'DURABLE');

    expect($categorieId)->toBeInt();

    $categorie = CategorieMateriel::query()->findOrFail($categorieId);

    expect($categorie->code)->toBe('MAT-PC')
        ->and($categorie->nom)->toBe('Ordinateurs')
        ->and($categorie->nature)->toBe(NatureMateriel::Durable)
        ->and($categorie->actif)->toBeTrue()
        ->and($categorie->ordre)->toBe(0);
});

it('liste les catégories par ordre', function () {
    $this->catalogue->creer('MAT-B', 'Matériel B', 'durable', 10);
    $this->catalogue->creer('MAT-A', 'Matériel A', 'durable', 1);

    expect(array_column($this->catalogue->lister(), 'code'))->toBe(['MAT-A', 'MAT-B']);
});

it('refuse un code déjà utilisé', function () {
    $this->catalogue->creer('MAT-PC', 'Ordinateurs', 'durable');

    expect(fn () => $this->catalogue->creer('MAT-PC', 'Autre libellé', 'durable'))
        ->toThrow(CategorieMaterielCodeDejaUtiliseException::class);

    $autreCategorie = $this->catalogue->creer('MAT-TABLE', 'Tables', 'durable');

    expect(fn () => $this->catalogue->modifier($autreCategorie, 'MAT-PC', 'Tables', 'durable', 0))
        ->toThrow(CategorieMaterielCodeDejaUtiliseException::class);

    expect(CategorieMateriel::query()->findOrFail($autreCategorie)->code)->toBe('MAT-TABLE');
});

it('exige un code et un nom pour une catégorie', function () {
    expect(fn () => $this->catalogue->creer('', 'Sans code', 'durable'))
        ->toThrow(CategorieMaterielChampsInvalidesException::class);

    expect(fn () => $this->catalogue->creer('MAT-SANS-NOM', '   ', 'durable'))
        ->toThrow(CategorieMaterielChampsInvalidesException::class);
});

it('modifie une catégorie existante', function () {
    $categorieId = $this->catalogue->creer('MAT-ABC', 'Ancien nom', 'durable', 2);

    $this->catalogue->modifier($categorieId, 'MAT-XYZ', 'Nouveau nom', 'CONSOMMABLE', 5);

    $categorie = CategorieMateriel::query()->findOrFail($categorieId);

    expect($categorie->code)->toBe('MAT-XYZ')
        ->and($categorie->nom)->toBe('Nouveau nom')
        ->and($categorie->nature)->toBe(NatureMateriel::Consommable)
        ->and($categorie->ordre)->toBe(5);
});

it('désactive une catégorie sans la supprimer', function () {
    $categorieId = $this->catalogue->creer('MAT-PAPIER', 'Papeterie', 'consommable');

    $this->catalogue->desactiver($categorieId);

    expect(array_column($this->catalogue->lister(), 'id'))->not->toContain($categorieId)
        ->and(array_column($this->catalogue->lister(true), 'id'))->toContain($categorieId)
        ->and(CategorieMateriel::query()->whereKey($categorieId)->exists())->toBeTrue()
        ->and($this->catalogue->nature($categorieId))->toBe('consommable');

    $this->catalogue->activer($categorieId);

    expect(array_column($this->catalogue->lister(), 'id'))->toContain($categorieId);
});

it('associe la nature DURABLE à une catégorie', function () {
    $categorieId = $this->catalogue->creer('MAT-MOBILIER', 'Mobilier', 'DURABLE');

    expect($this->catalogue->nature($categorieId))->toBe('durable')
        ->and(NatureMateriel::Durable->label())->toBe('Durable');
});

it('associe la nature CONSOMMABLE à une catégorie', function () {
    $categorieId = $this->catalogue->creer('MAT-FOURNITURE', 'Fournitures consommables', 'CONSOMMABLE');

    expect($this->catalogue->nature($categorieId))->toBe('consommable')
        ->and(NatureMateriel::Consommable->label())->toBe('Consommable');
});

it('refuse une nature non autorisée', function () {
    expect(fn () => $this->catalogue->creer('MAT-X', 'Nature inconnue', 'perissable'))
        ->toThrow(NatureMaterielInvalideException::class);
});

it('enregistre un équipement avec une catégorie valide', function () {
    $categorieId = $this->catalogue->creer('MAT-IMP', 'Imprimantes', 'durable');

    $equipement = $this->equipements->enregistrerEquipement(donneesEquipementCatalogue([
        'numero_identification' => 'EQ-CAT-001',
        'categorie_id' => $categorieId,
    ]));

    expect((int) $equipement->categorie_id)->toBe($categorieId)
        ->and($equipement->categorie()->first()?->nom)->toBe('Imprimantes')
        ->and($this->equipements->natureEquipement((int) $equipement->id))->toBe('durable');
});

it('refuse une catégorie inconnue pour un nouvel équipement', function () {
    expect(fn () => $this->equipements->enregistrerEquipement(donneesEquipementCatalogue([
        'numero_identification' => 'EQ-CAT-002',
        'categorie_id' => 424242,
    ])))->toThrow(CategorieMaterielInconnueException::class);

    expect(Equipement::query()->where('numero_identification', 'EQ-CAT-002')->exists())->toBeFalse();

    expect(fn () => $this->catalogue->nature(424242))->toThrow(CategorieMaterielInconnueException::class);
});

it('refuse une catégorie inactive pour un nouvel équipement', function () {
    $categorieId = $this->catalogue->creer('MAT-ARCHIVE', 'Archives', 'durable');
    $this->catalogue->desactiver($categorieId);

    expect(fn () => $this->equipements->enregistrerEquipement(donneesEquipementCatalogue([
        'numero_identification' => 'EQ-CAT-003',
        'categorie_id' => $categorieId,
    ])))->toThrow(CategorieMaterielInactiveException::class);

    expect(Equipement::query()->where('numero_identification', 'EQ-CAT-003')->exists())->toBeFalse();
});

it('conserve la catégorie legacy equipements.categorie', function () {
    $categorieId = $this->catalogue->creer('MAT-ECRAN', 'Écrans', 'durable');

    $equipement = $this->equipements->enregistrerEquipement(donneesEquipementCatalogue([
        'numero_identification' => 'EQ-CAT-004',
        'categorie' => 'Ancienne catégorie libre',
        'categorie_id' => $categorieId,
    ]));

    $equipement->refresh();

    expect($equipement->categorie)->toBe('Ancienne catégorie libre')
        ->and((int) $equipement->categorie_id)->toBe($categorieId);
});

it('conserve un équipement legacy sans categorie_id', function () {
    $equipement = $this->equipements->enregistrerEquipement(donneesEquipementCatalogue([
        'numero_identification' => 'EQ-CAT-005',
        'categorie' => 'Non catégorisé',
    ]));

    $equipement->refresh();

    expect($equipement->categorie_id)->toBeNull()
        ->and($equipement->categorie)->toBe('Non catégorisé')
        ->and($this->equipements->natureEquipement((int) $equipement->id))->toBeNull();
});

it('refuse la suppression d\'une catégorie utilisée', function () {
    $categorieId = $this->catalogue->creer('MAT-UTILISE', 'Utilisée', 'durable');

    $this->equipements->enregistrerEquipement(donneesEquipementCatalogue([
        'numero_identification' => 'EQ-CAT-006',
        'categorie_id' => $categorieId,
    ]));

    expect(fn () => $this->catalogue->supprimer($categorieId))
        ->toThrow(CategorieMaterielUtiliseeException::class);

    expect(CategorieMateriel::query()->whereKey($categorieId)->exists())->toBeTrue();
});

it('supprime une catégorie inutilisée', function () {
    $categorieId = $this->catalogue->creer('MAT-LIBRE', 'Jamais utilisée', 'durable');

    $this->catalogue->supprimer($categorieId);

    expect(CategorieMateriel::query()->whereKey($categorieId)->exists())->toBeFalse();
});

it('affiche les pages Filament du catalogue et de l\'inventaire', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $categorieId = $this->catalogue->creer('MAT-PC', 'Ordinateurs', 'durable');

    $equipement = $this->equipements->enregistrerEquipement(donneesEquipementCatalogue([
        'numero_identification' => 'EQ-CAT-FILAMENT',
        'categorie' => 'Libellé legacy',
        'categorie_id' => $categorieId,
    ]));

    $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
    $fondateur->assignRole('Fondateur');
    $this->actingAs($fondateur);

    $this->get(CategorieMaterielResource::getUrl())->assertSuccessful();
    $this->get(CategorieMaterielResource::getUrl('create'))->assertSuccessful();
    $this->get(CategorieMaterielResource::getUrl('edit', ['record' => $categorieId]))->assertSuccessful();
    $this->get(EquipementResource::getUrl())->assertSuccessful();
    $this->get(EquipementResource::getUrl('edit', ['record' => $equipement]))->assertSuccessful();
});
