<?php

declare(strict_types=1);

use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\SectionScolaire;
use Database\Seeders\LocalDevelopmentSeeder;

it('keeps the development school reference data and identifiers on repeated seeding', function (): void {
    $this->seed(LocalDevelopmentSeeder::class);
    $cycles = Cycle::orderBy('ordre')->pluck('id', 'code')->all();
    $niveaux = Niveau::orderBy('ordre')->get(['id', 'code', 'cycle_id', 'ordre'])->toArray();
    $sections = SectionScolaire::orderBy('id')->pluck('id', 'code')->all();
    expect(array_keys($cycles))->toBe(['MAT', 'PRI', 'COL', 'LYC'])
        ->and($niveaux)->toHaveCount(13)
        ->and(array_keys($sections))->toBe(['FR', 'EN']);
    foreach ($niveaux as $niveau) {
        expect($niveau['cycle_id'])->toBeIn(array_values($cycles));
    }
    $this->seed(LocalDevelopmentSeeder::class);
    expect(Cycle::orderBy('ordre')->pluck('id', 'code')->all())->toBe($cycles)
        ->and(Niveau::orderBy('ordre')->get(['id', 'code', 'cycle_id', 'ordre'])->toArray())->toBe($niveaux)
        ->and(SectionScolaire::orderBy('id')->pluck('id', 'code')->all())->toBe($sections);
});
