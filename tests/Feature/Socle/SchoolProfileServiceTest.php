<?php

declare(strict_types=1);

use App\Modules\Socle\Contracts\SchoolProfileContract;
use App\Modules\Socle\Data\SchoolProfileDTO;
use App\Modules\Socle\Models\ConfigEtablissement;

it('projects the single existing establishment configuration as an immutable public profile', function (): void {
    $configuration = ConfigEtablissement::query()->create([
        'nom' => 'Ambassadors Educational Complex',
        'sigle' => 'AEC',
        'telephone' => '+237 670 097 386',
        'devise' => 'XAF',
        'heure_ouverture' => '07:30:00',
        'tolerance_retard_minutes' => 12,
        'jours_ouvrables' => [1, 2, 3, 4, 5],
    ]);

    $profile = app(SchoolProfileContract::class)->profile();

    expect($profile)->toBeInstanceOf(SchoolProfileDTO::class)
        ->and($profile->id)->toBe($configuration->id)
        ->and($profile->name)->toBe('Ambassadors Educational Complex')
        ->and($profile->workingDays)->toBe([1, 2, 3, 4, 5])
        ->and($profile->lateToleranceMinutes)->toBe(12)
        ->and((new ReflectionClass($profile))->isReadOnly())->toBeTrue();
});
