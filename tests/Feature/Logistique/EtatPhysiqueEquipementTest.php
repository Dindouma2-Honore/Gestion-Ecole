<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Enums\EtatPhysiqueMateriel;
use App\Modules\Logistique\Models\Equipement;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->service ??= app(EquipementServiceInterface::class);
    Storage::fake('documents');
    $user = User::factory()->create(['statut' => 'actif']);
    $this->actingAs($user);
});

it('enregistre un equipement avec un etat physique valide', function () {
    $equipement = $this->service->enregistrerEquipement([
        'nom' => 'Bureau',
        'numero_identification' => 'EQ-ETAT-01',
        'etat' => EtatPhysiqueMateriel::Moyen->value,
        'date_acquisition' => now(),
    ]);

    expect($equipement->etat)->toBe(EtatPhysiqueMateriel::Moyen->value);
});

it('modifie l etat physique d un equipement via le service', function () {
    $equipement = Equipement::create([
        'nom' => 'Chaise',
        'numero_identification' => 'EQ-ETAT-02',
        'etat' => EtatPhysiqueMateriel::Bon->value,
        'date_acquisition' => now(),
    ]);

    $this->service->modifierEtatPhysique($equipement->id, EtatPhysiqueMateriel::Mauvais);

    expect($equipement->fresh()->etat)->toBe(EtatPhysiqueMateriel::Mauvais->value);
});

it('refuse un etat physique invalide', function () {
    $equipement = Equipement::create([
        'nom' => 'Table',
        'numero_identification' => 'EQ-ETAT-03',
        'etat' => EtatPhysiqueMateriel::Bon->value,
        'date_acquisition' => now(),
    ]);

    expect(fn () => $this->service->modifierEtatPhysique($equipement->id, 'etat_fantome'))
        ->toThrow(InvalidArgumentException::class);
});

it('conserve la compatibilite avec les etats legacy et le workflow de panne et mise au rebut', function () {
    $equipement = Equipement::create([
        'nom' => 'Imprimante legacy',
        'numero_identification' => 'EQ-ETAT-04',
        'etat' => EtatPhysiqueMateriel::Bon->value,
        'date_acquisition' => now(),
    ]);

    // Déclarer panne (met en a_reparer)
    $this->service->declarerPanne($equipement->id, 'Panne alim');
    expect($equipement->fresh()->etat)->toBe(EtatPhysiqueMateriel::AReparer->value);

    // Mettre au rebut (met en mis_au_rebut)
    $this->service->mettreAuRebut($equipement->id, 'Obsolète');
    expect($equipement->fresh()->etat)->toBe(EtatPhysiqueMateriel::MisAuRebut->value);
});
