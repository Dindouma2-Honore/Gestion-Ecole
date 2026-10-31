<?php

declare(strict_types=1);

use App\Filament\Support\NiveauScopeSelect;
use App\Modules\Pedagogie\Services\OrganisationPedagogiqueContext;
use App\Modules\RH\Models\Employe;
use App\Modules\Scolarite\Contracts\AcademicStructureCatalogContract;
use App\Modules\Scolarite\Contracts\AcademicStructureManagementContract;
use App\Modules\Scolarite\Contracts\AcademicStructureReadContract;
use App\Modules\Scolarite\Data\AcademicStructureItemDTO;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\Socle\Models\Reunion;

it('uses the public catalogue for selectors settings and pedagogical context', function (): void {
    $cycle = new AcademicStructureItemDTO(9001, 'Cycle test');
    $niveau = new AcademicStructureItemDTO(9002, 'Niveau test', 9001);
    $catalog = Mockery::mock(AcademicStructureCatalogContract::class);
    $catalog->shouldReceive('cycles')->andReturn([9001 => $cycle]);
    $catalog->shouldReceive('niveaux')->andReturn([9002 => $niveau]);
    app()->instance(AcademicStructureCatalogContract::class, $catalog);
    $read = Mockery::mock(AcademicStructureReadContract::class);
    $read->shouldReceive('niveau')->with(9002)->andReturn($niveau);
    app()->instance(AcademicStructureReadContract::class, $read);
    $settings = app(ParametrageServiceContract::class);
    expect($settings->getNiveau(9002))->toBe($niveau)
        ->and($settings->getTousLesNiveaux())->toBe([9002 => $niveau])
        ->and(NiveauScopeSelect::make()->getOptions())->toBe([9002 => 'Niveau test']);
    $context = app(OrganisationPedagogiqueContext::class);
    $context->reset();
    expect($context->cycles()[9001]['nom'])->toBe('Cycle test')
        ->and($context->niveaux()[9002]['cycle_id'])->toBe(9001);
    foreach ([Reunion::class, JourFerie::class, Employe::class] as $model) {
        $record = new $model;
        expect($record->niveau)->toBeNull()->and(method_exists($record, 'niveau'))->toBeFalse();
        $record->niveau_id = 9002;
        expect($record->niveau)->toBe($niveau)->and($record->niveau_id)->toBe(9002);
    }
});

it('imports levels idempotently through public contracts preserving legacy metadata', function (): void {
    $management = app(AcademicStructureManagementContract::class);
    $first = $management->importNiveau('source:1', 'Initial', 'SRC', 10, 'Description');
    $updated = $management->importNiveau('source:1', 'Renommé', 'SRC', 20, 'Mise à jour');
    expect($updated->id)->toBe($first->id)->and($updated->description)->toBe('Mise à jour')
        ->and(app(AcademicStructureReadContract::class)->niveauByLegacyId('source:1'))->toEqual($updated);
});
