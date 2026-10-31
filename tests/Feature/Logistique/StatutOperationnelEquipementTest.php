<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Logistique\Contracts\EquipementServiceInterface;
use App\Modules\Logistique\Enums\EtatPhysiqueMateriel;
use App\Modules\Logistique\Enums\StatutOperationnelMateriel;
use App\Modules\Logistique\Models\Equipement;
use App\Modules\Logistique\Models\EquipementHistoriqueStatut;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->service ??= app(EquipementServiceInterface::class);
    Storage::fake('documents');
    $user = User::factory()->create(['statut' => 'actif']);
    $this->actingAs($user);
});

it('enregistre un equipement avec le statut operationnel par defaut en_stock', function () {
    $equipement = $this->service->enregistrerEquipement([
        'nom' => 'Ordinateur portable',
        'numero_identification' => 'EQ-STAT-01',
        'date_acquisition' => now(),
    ]);

    expect($equipement->statut_operationnel)->toBe(StatutOperationnelMateriel::EnStock->value);
});

it('change le statut operationnel et cree une entree dans l historique append-only', function () {
    $equipement = Equipement::create([
        'nom' => 'Videooprojecteur',
        'numero_identification' => 'EQ-STAT-02',
        'statut_operationnel' => StatutOperationnelMateriel::EnStock->value,
        'date_acquisition' => now(),
    ]);

    $this->service->changerStatut($equipement->id, StatutOperationnelMateriel::EnMaintenance->value, 'Panne lampe');

    $equipementFresh = $equipement->fresh();
    expect($equipementFresh->statut_operationnel)->toBe(StatutOperationnelMateriel::EnMaintenance->value);

    $historique = $this->service->getHistoriqueStatuts($equipement->id);
    expect($historique)->toHaveCount(1)
        ->and($historique->first()->ancien_statut)->toBe(StatutOperationnelMateriel::EnStock->value)
        ->and($historique->first()->nouveau_statut)->toBe(StatutOperationnelMateriel::EnMaintenance->value)
        ->and($historique->first()->motif)->toBe('Panne lampe');
});

it('prouve l independance entre etat physique et statut operationnel', function () {
    $equipement = Equipement::create([
        'nom' => 'Serveur',
        'numero_identification' => 'EQ-STAT-03',
        'etat' => EtatPhysiqueMateriel::Bon->value,
        'statut_operationnel' => StatutOperationnelMateriel::EnStock->value,
        'date_acquisition' => now(),
    ]);

    // Modifier l'état physique à MAUVAIS et le statut opérationnel à EN_MAINTENANCE
    $this->service->modifierEtatPhysique($equipement->id, EtatPhysiqueMateriel::Mauvais->value);
    $this->service->changerStatut($equipement->id, StatutOperationnelMateriel::EnMaintenance->value, 'Maintenance préventive');

    $fresh = $equipement->fresh();
    expect($fresh->etat)->toBe(EtatPhysiqueMateriel::Mauvais->value)
        ->and($fresh->statut_operationnel)->toBe(StatutOperationnelMateriel::EnMaintenance->value);
});

it('refuse une transition de statut interdite', function () {
    $equipement = Equipement::create([
        'nom' => 'Table',
        'numero_identification' => 'EQ-STAT-04',
        'statut_operationnel' => StatutOperationnelMateriel::Reforme->value,
        'date_acquisition' => now(),
    ]);

    expect(fn () => $this->service->changerStatut($equipement->id, StatutOperationnelMateriel::EnStock->value))
        ->toThrow(InvalidArgumentException::class);
});

it('reformé conserve l equipement consultable sans le supprimer', function () {
    $equipement = Equipement::create([
        'nom' => 'Ancien ecran',
        'numero_identification' => 'EQ-STAT-05',
        'statut_operationnel' => StatutOperationnelMateriel::EnStock->value,
        'date_acquisition' => now(),
    ]);

    $this->service->mettreAuRebut($equipement->id, 'Hors d’usage');

    $fresh = $equipement->fresh();
    expect($fresh->statut_operationnel)->toBe(StatutOperationnelMateriel::Reforme->value)
        ->and(Equipement::find($equipement->id))->not->toBeNull();
});

it('leve une exception pour un equipement inexistant lors du changement de statut', function () {
    expect(fn () => $this->service->changerStatut(99999, StatutOperationnelMateriel::Affecte->value))
        ->toThrow(ModelNotFoundException::class);
});
