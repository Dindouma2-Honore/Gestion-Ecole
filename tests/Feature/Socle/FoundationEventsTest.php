<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\StaffAccountWhatsApp;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Contracts\Events\UserAccountCreated;
use App\Modules\Socle\Contracts\Events\UserAccountReactivated;
use App\Modules\Socle\Contracts\Events\UserFeatureAccessChanged;
use App\Modules\Socle\Contracts\GroupeServiceContract;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\Groupe;
use App\Modules\Socle\Services\StaffAccountService;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

it('distinguishes staff creation from reuse and notifies only after commit', function (bool $reuse, bool $commit): void {
    Role::findOrCreate('Fondateur', 'web');
    Role::findOrCreate('Comptable', 'web');
    $actor = User::factory()->create(['statut' => 'actif']);
    $actor->assignRole('Fondateur');
    $this->actingAs($actor);
    $placeholder = $reuse ? User::factory()->create(['email' => 'placeholder@ambassadors.local', 'statut' => 'desactive']) : null;
    $employee = Employe::create(['matricule' => 'EVT-001', 'nom' => 'Test', 'prenom' => 'Events', 'email' => 'events@example.test',
        'telephone' => '670097386', 'poste' => 'Comptable', 'date_embauche' => today(), 'statut' => 'actif', 'user_id' => $placeholder?->id]);
    $sent = [];
    $whatsapp = Mockery::mock(StaffAccountWhatsApp::class);
    $whatsapp->shouldReceive('dispatch')->times($commit ? 1 : 0)->andReturnUsing(function (User $user) use (&$sent) {
        $sent[] = $user->id;

        return new CommunicationMessage;
    });
    app()->instance(StaffAccountWhatsApp::class, $whatsapp);
    $events = [];
    foreach ([UserAccountCreated::class, UserAccountReactivated::class] as $event) {
        Event::listen($event, function ($payload) use (&$events): void {
            $events[] = $payload;
        });
    }
    DB::beginTransaction();
    $user = app(StaffAccountService::class)->create(['employe_id' => $employee->id, 'email' => 'events@example.test', 'role' => 'Comptable']);
    expect($events)->toBe([])->and($sent)->toBe([]);
    if ($reuse) {
        expect($user->id)->toBe($placeholder->id);
    }
    if ($commit) {
        DB::commit();
        expect($events)->toHaveCount(1)->and($events[0])->toBeInstanceOf($reuse ? UserAccountReactivated::class : UserAccountCreated::class)
            ->and($events[0]->userId)->toBe($user->id)->and($sent)->toBe([$user->id]);
    } else {
        DB::rollBack();
        expect($events)->toBe([])->and($sent)->toBe([]);
        if ($reuse) {
            expect($placeholder->fresh()->email)->toBe('placeholder@ambassadors.local');
        } else {
            expect(User::find($user->id))->toBeNull();
        }
    }
})->with([[false, true], [true, true], [false, false], [true, false]]);

it('sends assignment notifications from Communication after commit and discards rolled back ones', function (string $source, bool $commit): void {
    $user = User::factory()->create(['statut' => 'actif']);
    $inactive = User::factory()->create(['statut' => 'desactive']);
    Role::findOrCreate('Directeur', 'web');
    $user->assignRole('Directeur');
    $inactive->assignRole('Directeur');
    $feature = Fonctionnalite::create(['code' => 'events.test', 'nom' => 'Droit initial', 'categorie' => 'Tests', 'actif' => true]);
    $group = Groupe::create(['nom' => 'Groupe initial']);
    $group->membres()->attach([$user->id, $inactive->id]);
    $sent = [];
    $notifications = Mockery::mock(NotificationServiceContract::class);
    $notifications->shouldReceive('envoyer')->times($commit ? 1 : 0)->andReturnUsing(function ($channel, $code, $recipient, $data) use (&$sent) {
        $sent[] = [$channel, $code, $recipient->id, $data];

        return (object) [];
    });
    app()->instance(NotificationServiceContract::class, $notifications);
    DB::beginTransaction();
    match ($source) {
        'user' => app(HabilitationServiceContract::class)->accorderModulePourUser($user, 'events.test'),
        'role' => app(HabilitationServiceContract::class)->desactiverModulePourRole('Directeur', 'events.test', 'Test retrait'),
        'group' => app(GroupeServiceContract::class)->retirerModulePourGroupe($group, 'events.test', 'Test retrait'),
    };
    expect($sent)->toBe([]);
    $feature->update(['nom' => 'Renommé']);
    $group->update(['nom' => 'Renommé']);
    if ($commit) {
        DB::commit();
        expect($sent)->toHaveCount(1)
            ->and($sent[0][0])->toBe('in_app')->and($sent[0][1])->toBe('habilitation_modifiee')
            ->and($sent[0][2])->toBe($user->id)
            ->and($sent[0][3]['fonctionnalite'])->toBe('Droit initial')
            ->and($sent[0][3]['action'])->toBe($source === 'user' ? 'accordee' : 'retiree');
        if ($source === 'group') {
            expect($sent[0][3]['groupe'])->toBe('Groupe initial');
        } else {
            expect($sent[0][3])->not->toHaveKey('groupe');
        }
    } else {
        DB::rollBack();
        expect($sent)->toBe([]);
    }
})->with([['user', true], ['role', true], ['group', true], ['user', false], ['role', false], ['group', false]]);

it('exposes immutable scalar events and removes notification coupling from the two producers', function (): void {
    foreach ([UserAccountCreated::class, UserAccountReactivated::class, UserFeatureAccessChanged::class] as $event) {
        $reflection = new ReflectionClass($event);
        expect($reflection->isReadOnly())->toBeTrue()->and($reflection->implementsInterface(ShouldDispatchAfterCommit::class))->toBeTrue();
        foreach ($reflection->getProperties() as $property) {
            expect($property->getType()->getName())->toBeIn(['int', 'string', 'bool']);
        }
    }
    foreach (['GroupeService', 'HabilitationService'] as $service) {
        expect(file_get_contents(app_path('Modules/Socle/Services/'.$service.'.php')))->not->toContain('Modules\\Communication\\');
    }
});
