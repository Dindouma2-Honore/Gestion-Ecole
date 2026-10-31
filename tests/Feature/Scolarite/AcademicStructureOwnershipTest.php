<?php

declare(strict_types=1);

use App\Modules\RH\Models\Employe;
use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use App\Modules\Scolarite\Data\AcademicStructureItemDTO;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Filiere;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\SectionScolaire;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\Socle\Models\Reunion;
use Illuminate\Support\Facades\DB;

it('preserves existing tables identifiers data and writes', function (string $model, string $table): void {
    DB::table($table)->insert(['id' => 8101, 'nom' => 'Existant', 'code' => 'EXISTANT']);

    expect((new $model)->getTable())->toBe($table)
        ->and($model::findOrFail(8101)->nom)->toBe('Existant');

    $record = $model::findOrFail(8101);
    expect($record)->toBeInstanceOf($model);
    $record->update(['nom' => 'Modifié']);
    expect($model::findOrFail(8101)->nom)->toBe('Modifié');
    $model::findOrFail(8101)->update(['nom' => 'Scolarité']);
    expect($model::findOrFail(8101)->nom)->toBe('Scolarité');

    if ($table !== 'niveaux') {
        $record->update(['actif' => false]);
        expect($model::findOrFail(8101)->actif)->toBeFalse();
    }
})->with([
    [Filiere::class, 'filieres'],
    [Cycle::class, 'cycles'],
    [Niveau::class, 'niveaux'],
    [SectionScolaire::class, 'sections_scolaires'],
]);

it('preserves both directions of cycle niveau relations after reloading', function (): void {
    $cycle = Cycle::create(['nom' => 'Cycle', 'code' => 'C']);
    $niveau = $cycle->niveaux()->create(['nom' => 'Niveau', 'code' => 'N']);
    expect($niveau)->toBeInstanceOf(Niveau::class)
        ->and($niveau->cycle->is($cycle))->toBeTrue()
        ->and(Cycle::findOrFail($cycle->id)->niveaux->sole()->is($niveau))->toBeTrue()
        ->and(Niveau::findOrFail($niveau->id)->cycle->is($cycle))->toBeTrue();
});

it('keeps legacy consumer niveau relationships working', function (string $consumer): void {
    $niveau = Niveau::create(['nom' => 'Niveau', 'code' => 'N']);
    $record = new $consumer;
    $record->niveau_id = $niveau->id;
    expect($record->niveau)->toBeInstanceOf(AcademicStructureItemDTO::class)
        ->and($record->niveau->id)->toBe($niveau->id);
})->with([
    Reunion::class,
    JourFerie::class,
    Employe::class,
]);

it('resolves public contracts without the Socle port and returns keyed DTOs', function (string $contract): void {
    $cycle = Cycle::create(['nom' => 'Cycle', 'code' => 'C', 'actif' => false]);
    $niveau = Niveau::create(['nom' => 'Niveau', 'code' => 'N', 'cycle_id' => $cycle->id]);
    $filiere = Filiere::create(['nom' => 'Filière', 'code' => 'F']);
    $section = SectionScolaire::create(['nom' => 'Section', 'code' => 'S']);
    $service = app($contract);

    foreach (['cycles' => $cycle, 'niveaux' => $niveau, 'filieres' => $filiere, 'sections' => $section] as $method => $record) {
        $dto = $service->$method()[$record->id];
        expect($dto)->toBeInstanceOf(AcademicStructureItemDTO::class)
            ->and($dto->id)->toBe($record->id)
            ->and($dto->name)->toBe($record->nom);
    }
    expect($service->cycles()[$cycle->id]->active)->toBeFalse()
        ->and($service->niveaux()[$niveau->id]->parentId)->toBe($cycle->id);
})->with([AcademicStructureCatalogContract::class]);
