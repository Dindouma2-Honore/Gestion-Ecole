<?php

declare(strict_types=1);

use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use App\Modules\Scolarite\Contracts\AcademicStructureManagementContract;
use App\Modules\Scolarite\Contracts\AcademicStructureReadContract;
use App\Modules\Scolarite\Data\AcademicStructureItemDTO;
use App\Modules\Scolarite\Services\AcademicStructureService;
use Illuminate\Validation\ValidationException;

it('creates reads and replaces school reference records using only DTOs', function (string $type, string $read, string $catalog): void {
    $management = app(AcademicStructureManagementContract::class);
    $reader = app(AcademicStructureReadContract::class);
    $lists = app(AcademicStructureCatalogContract::class);
    expect($reader->$read(999999))->toBeNull();
    expect($management->{'update'.$type}(999999, 'Absent', 'ABSENT'))->toBeNull();
    $created = $management->{'create'.$type}('Initial', 'INITIAL');
    expect($created)->toBeInstanceOf(AcademicStructureItemDTO::class)
        ->and($reader->$read($created->id))->toEqual($created)
        ->and($lists->$catalog()[$created->id])->toEqual($created);
    $updated = $management->{'update'.$type}($created->id, 'Modifié', 'INITIAL');
    expect($updated->id)->toBe($created->id)
        ->and($reader->$read($created->id)->name)->toBe('Modifié');
    $this->expectException(ValidationException::class);
    $management->{'create'.$type}('Doublon', 'INITIAL');
})->with([
    ['Cycle', 'cycle', 'cycles'], ['Niveau', 'niveau', 'niveaux'],
    ['Filiere', 'filiere', 'filieres'], ['SectionScolaire', 'section', 'sections'],
]);

it('filters existing active flags and the cycle niveau hierarchy with stable ID keys', function (): void {
    $m = app(AcademicStructureManagementContract::class);
    $c = app(AcademicStructureCatalogContract::class);
    $cycle = $m->createCycle('Cycle', 'C', 'Description', false, 2);
    $first = $m->createCycle('Premier', 'P', order: 1);
    $niveau = $m->createNiveau('Niveau', 'N', $cycle->id, 3);
    $m->createNiveau('Autre', 'A', $first->id);
    expect(array_keys($c->cycles()))->toBe([$first->id, $cycle->id])
        ->and(array_keys($c->cycles(false)))->toBe([$cycle->id])
        ->and(array_keys($c->cycles(true)))->toBe([$first->id])
        ->and(array_keys($c->niveaux($cycle->id)))->toBe([$niveau->id])
        ->and($c->niveaux(999999))->toBe([])
        ->and($cycle->description)->toBe('Description')->and($cycle->order)->toBe(2)
        ->and($niveau->code)->toBe('N');
    $detached = $m->updateNiveau($niveau->id, 'Niveau', 'N');
    expect($detached->parentId)->toBeNull();
    foreach (['Filiere' => 'filieres', 'SectionScolaire' => 'sections'] as $type => $method) {
        $inactive = $m->{'create'.$type}('Inactif', 'OFF', active: false);
        $active = $m->{'create'.$type}('Actif', 'ON');
        expect(array_keys($c->$method(false)))->toBe([$inactive->id])
            ->and(array_keys($c->$method(true)))->toBe([$active->id]);
    }
});

it('rejects nonexistent parents without changing existing records', function (): void {
    $m = app(AcademicStructureManagementContract::class);
    $niveau = $m->createNiveau('Initial', 'N');
    try {
        $m->updateNiveau($niveau->id, 'Incorrect', 'N', 999999);
        $this->fail('Expected validation failure');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('cycle_id');
    }
    expect(app(AcademicStructureReadContract::class)->niveau($niveau->id)->name)->toBe('Initial');
});

it('binds the three distinct public contracts to the internal service', function (): void {
    foreach ([AcademicStructureReadContract::class, AcademicStructureCatalogContract::class, AcademicStructureManagementContract::class] as $contract) {
        expect(app($contract))->toBeInstanceOf(AcademicStructureService::class)->toBeInstanceOf($contract);
    }
});
