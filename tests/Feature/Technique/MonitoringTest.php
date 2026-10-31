<?php

use App\Models\User;
use App\Modules\Technique\Contracts\MonitoringServiceContract;
use App\Modules\Technique\Filament\Pages\TechnicalDashboard;
use App\Modules\Technique\Models\TechnicalMeasurement;
use App\Modules\Technique\Services\TechnicalAccess;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

it('reste inconnu sans mesure et après expiration', function () {
    $monitoring = app(MonitoringServiceContract::class);
    expect($monitoring->snapshot()['scheduler']['status'])->toBe('UNKNOWN');
    $monitoring->heartbeat('scheduler');
    expect($monitoring->snapshot()['scheduler']['status'])->toBe('AVAILABLE');
    $this->travel(181)->seconds();
    expect($monitoring->snapshot()['scheduler']['status'])->toBe('UNKNOWN');
});

it('mesure la connexion sans prétendre connaître la réplication', function () {
    config(['technique.storage_disks' => []]);
    $this->artisan('technique:monitor')->assertSuccessful();
    $snapshot = app(MonitoringServiceContract::class)->snapshot();
    expect($snapshot['database.primary']['status'])->toBe('AVAILABLE')
        ->and($snapshot['database.primary']['metrics'])->toHaveKey('latency_ms')
        ->and($snapshot['queue.worker']['status'])->toBe('UNKNOWN');
});

it('reste consultable lorsque la table de mesures est indisponible', function () {
    Schema::drop('technical_measurements');
    expect(app(MonitoringServiceContract::class)->snapshot()['database.primary']['status'])->toBe('UNKNOWN');
});

it('refuse les utilisateurs sans permission même administrateurs', function () {
    $user = User::factory()->create(['statut' => 'actif']);
    $user->assignRole(Role::findOrCreate('Admin', 'web'));
    expect(TechnicalAccess::allows($user))->toBeFalse();
    $this->actingAs($user)->get(TechnicalDashboard::getUrl(panel: 'admin'))->assertForbidden();
});

it('autorise le fondateur actif et refuse un compte désactivé', function () {
    $user = User::factory()->create(['statut' => 'actif']);
    $user->assignRole(Role::findOrCreate('Fondateur', 'web'));
    expect(TechnicalAccess::allows($user))->toBeTrue();
    $this->actingAs($user)->get(TechnicalDashboard::getUrl(panel: 'admin'))->assertOk()->assertSee('Inconnu');
    $user->statut = 'inactif';
    expect(TechnicalAccess::allows($user))->toBeFalse();
});

it('ne conserve aucun message brut pour un stockage invalide', function () {
    config(['technique.storage_disks' => ['missing']]);
    app(MonitoringServiceContract::class)->collect();
    $measurement = TechnicalMeasurement::where('component', 'storage.missing')->firstOrFail();
    expect($measurement->status)->toBe('UNKNOWN')->and($measurement->metrics)->toBe([]);
});

it('exige la permission et respecte le retrait de l habilitation', function () {
    $user = User::factory()->create(['statut' => 'actif']);
    $user->givePermissionTo('technique.view');
    $habilitations = Mockery::mock(\App\Modules\Socle\Contracts\HabilitationServiceContract::class);
    $habilitations->shouldReceive('moduleActifPourUser')->with($user, 'module.technique')->twice()->andReturn(true, false);
    app()->instance(\App\Modules\Socle\Contracts\HabilitationServiceContract::class, $habilitations);
    expect(TechnicalAccess::allows($user))->toBeTrue()
        ->and(TechnicalAccess::allows($user))->toBeFalse();
});
