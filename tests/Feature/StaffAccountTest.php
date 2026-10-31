<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Communication\Jobs\SendWhatsAppMessage;
use App\Modules\Communication\Models\CommunicationMessage;
use App\Modules\Communication\Services\StaffAccountWhatsApp;
use App\Modules\Communication\Services\WhatsAppTemplateValidator;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Contracts\Events\UserAccountCreated;
use App\Modules\Socle\Filament\Pages\ManageGroupePermissions;
use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use App\Modules\Socle\Filament\Pages\ManageUserPermissions;
use App\Modules\Socle\Filament\Pages\SocleDashboard;
use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Socle\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Socle\Filament\Resources\UserResource\Pages\ViewUser;
use App\Modules\Socle\Services\StaffAccountService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::factory()->create(['statut' => 'actif']);
        $actor->assignRole('Fondateur');
        $this->actingAs($actor);
        config(['whatsapp.enabled' => true, 'whatsapp.mode' => 'test', 'whatsapp.allowlist' => ['+237670097386'],
            'whatsapp.token' => 'fake', 'whatsapp.phone_id' => '123', 'whatsapp.waba_id' => '456', 'whatsapp.version' => 'v25.0', 'queue.default' => 'database']);
    }

    private function employee(): Employe
    {
        return Employe::create(['matricule' => 'EMP-TEST', 'nom' => 'Nana', 'prenom' => 'Paul', 'email' => 'paul@example.test',
            'telephone' => '670097386', 'poste' => 'Comptable', 'date_embauche' => today(), 'statut' => 'actif']);
    }

    private function createAccount(Employe $employee): User
    {
        return app(StaffAccountService::class)->create(['employe_id' => $employee->id, 'email' => $employee->email, 'role' => 'Comptable']);
    }

    public function test_creation_links_existing_staff_and_excludes_unrelated_accounts(): void
    {
        $employee = $this->employee();
        $user = $this->createAccount($employee);
        $this->assertSame($user->id, $employee->fresh()->user_id);
        $this->assertSame('Nana Paul', $user->name);
        $this->assertTrue($user->hasRole('Comptable'));
        $this->assertTrue($user->must_change_password);
        $this->assertSame([$user->id], UserResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame('blocked', CommunicationMessage::first()->status);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
        $this->expectException(ValidationException::class);
        $this->createAccount($employee);
    }

    public function test_creation_form_selects_staff_without_manual_identity_or_password(): void
    {
        $employee = $this->employee();
        Livewire::test(CreateUser::class)->assertSuccessful()
            ->fillForm(['employe_id' => $employee->id, 'email' => $employee->email, 'role' => 'Comptable'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertNotNull($employee->fresh()->user_id);
    }

    public function test_approved_invitation_sends_once_and_contains_login_and_reset_link(): void
    {
        $template = app(StaffAccountWhatsApp::class)->template();
        $components = [['type' => 'BODY', 'text' => $template->body]];
        $template->update(['local_status' => 'ACTIVE', 'meta_status' => 'APPROVED', 'provider_template_id' => '789',
            'provider_response' => ['name' => $template->provider_template_name, 'language' => 'fr'],
            'provider_components' => $components, 'content_hash' => app(WhatsAppTemplateValidator::class)->hash($components), 'last_synced_at' => now()]);
        $user = $this->createAccount($this->employee());
        $message = CommunicationMessage::firstOrFail();
        $this->assertSame('queued', $message->status);
        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.staff']]])]);
        $job = new SendWhatsAppMessage($message->id);
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        $this->assertSame('accepted', $message->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($user): bool {
            $values = data_get($request->data(), 'template.components.0.parameters');

            return $request['to'] === '237670097386' && $values[1]['text'] === $user->email
                && str_contains($values[2]['text'], 'password-reset/reset') && str_contains($values[2]['text'], 'token=');
        });
    }

    public function test_missing_staff_phone_keeps_account_and_blocks_notification_after_commit(): void
    {
        $employee = $this->employee();
        $employee->update(['telephone' => null]);
        $events = [];
        Event::listen(UserAccountCreated::class,
            function ($event) use (&$events): void {
                $events[] = $event;
            });
        DB::beginTransaction();
        $user = $this->createAccount($employee);
        $this->assertSame([], $events);
        $this->assertSame(0, CommunicationMessage::count());
        DB::commit();

        $this->assertCount(1, $events);
        $this->assertSame($user->id, $events[0]->userId);
        $this->assertSame($user->id, $employee->fresh()->user_id);
        $this->assertNotNull($user->fresh());
        $message = CommunicationMessage::sole();
        $this->assertSame('blocked', $message->status);
        $this->assertSame('invalid_international_number', $message->reason);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_consultation_and_deletion_keep_staff_record(): void
    {
        $employee = $this->employee();
        $user = $this->createAccount($employee);
        Livewire::test(ViewUser::class, ['record' => $user->id])->assertSuccessful()->assertSee($user->email);
        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$user])
            ->callTableAction('delete', $user);
        $this->assertNull(User::find($user->id));
        $this->assertNotNull($employee->fresh());
        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_modal_delete_removes_user_and_closes_modal(): void
    {
        $employee = $this->employee();
        $user = $this->createAccount($employee);

        $test = Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$user])
            ->mountTableAction('edit', $user);

        $action = $test->instance()->getTable()->getAction('edit');
        $action->record($user);
        $modalDelete = collect($action->getExtraModalFooterActions())->first(fn ($a) => $a->getName() === 'modalDelete');
        $this->assertNotNull($modalDelete);

        $modalDelete->call();

        $this->assertNull(User::find($user->id));
        $this->assertNotNull($employee->fresh());
        $this->assertNull($employee->fresh()->user_id);
        $test->call('$refresh')->assertCanNotSeeTableRecords([$user]);
    }

    public function test_unauthorized_actor_cannot_create_account(): void
    {
        $employee = $this->employee();
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        $this->expectException(AuthorizationException::class);
        $this->createAccount($employee);
    }

    public function test_password_activation_clears_forced_change(): void
    {
        $user = $this->createAccount($this->employee());
        event(new PasswordReset($user));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_creation_modal_creates_account_and_stays_on_list(): void
    {
        $employee = $this->employee();
        $component = Livewire::test(ListUsers::class)
            ->mountAction('create')
            ->assertActionMounted('create')
            ->fillForm(['employe_id' => $employee->id, 'email' => $employee->email, 'role' => 'Comptable'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNoRedirect();
        $user = $employee->fresh()->user;
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Comptable'));
        $this->assertSame(1, CommunicationMessage::where('idempotency_key', 'staff-account:'.$user->id)->count());
        $component->assertCanSeeTableRecords([$user]);
    }

    public function test_creation_with_manual_password_and_confirmation(): void
    {
        $employee = $this->employee();
        Livewire::test(ListUsers::class)
            ->mountAction('create')
            ->assertActionMounted('create')
            ->fillForm([
                'employe_id' => $employee->id,
                'email' => 'paul@ambassadors.com',
                'password' => 'Ambassadors2026!',
                'password_confirmation' => 'Ambassadors2026!',
                'role' => 'Comptable',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $user = $employee->fresh()->user;
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('Ambassadors2026!', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertSame('paul@ambassadors.com', $user->email);
    }

    public function test_creation_fails_when_password_confirmation_does_not_match(): void
    {
        $employee = $this->employee();
        Livewire::test(ListUsers::class)
            ->mountAction('create')
            ->assertActionMounted('create')
            ->fillForm([
                'employe_id' => $employee->id,
                'email' => 'paul@ambassadors.com',
                'password' => 'Password123!',
                'password_confirmation' => 'DifferentPassword!',
                'role' => 'Comptable',
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['password_confirmation']);

        $this->assertNull($employee->fresh()->user_id);
    }

    public function test_edit_modal_allows_updating_password(): void
    {
        $employee = $this->employee();
        $user = $this->createAccount($employee);

        Livewire::test(ListUsers::class)
            ->callTableAction('edit', $user, [
                'password' => 'NewSecretPassword2026!',
                'password_confirmation' => 'NewSecretPassword2026!',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(Hash::check('NewSecretPassword2026!', $user->fresh()->password));
    }

    public function test_edit_modal_preserves_password_when_left_empty(): void
    {
        $employee = $this->employee();
        $user = app(StaffAccountService::class)->create([
            'employe_id' => $employee->id,
            'email' => 'test@ambassadors.com',
            'role' => 'Comptable',
            'password' => 'InitialPassword123!',
        ]);

        Livewire::test(ListUsers::class)
            ->callTableAction('edit', $user, [
                'name' => 'Nana Paul Updated',
                'password' => '',
                'password_confirmation' => '',
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $user->fresh();
        $this->assertSame('Nana Paul Updated', $fresh->name);
        $this->assertTrue(Hash::check('InitialPassword123!', $fresh->password));
    }

    public function test_creation_upgrades_placeholder_account_to_active_account(): void
    {
        $employee = $this->employee();
        $placeholderUser = User::create([
            'name' => 'Placeholder Staff',
            'nom' => 'Nana',
            'prenom' => 'Paul',
            'email' => "personnel.{$employee->id}@ambassadors.local",
            'statut' => 'desactive',
            'facilg_legacy_id' => "personnel-access:{$employee->id}",
            'password' => Hash::make('random'),
        ]);
        $employee->update(['user_id' => $placeholderUser->id]);

        $initialUserCount = User::count();

        $upgradedUser = app(StaffAccountService::class)->create([
            'employe_id' => $employee->id,
            'email' => 'paul.nana@ambassadors.com',
            'role' => 'Comptable',
            'password' => 'NewActivePass123!',
        ]);

        $this->assertSame($placeholderUser->id, $upgradedUser->id);
        $this->assertSame($initialUserCount, User::count(), 'Should upgrade existing placeholder without creating duplicates');
        $this->assertSame('paul.nana@ambassadors.com', $upgradedUser->email);
        $this->assertSame('actif', $upgradedUser->statut);
        $this->assertNull($upgradedUser->facilg_legacy_id);
        $this->assertTrue($upgradedUser->hasRole('Comptable'));
        $this->assertTrue(Hash::check('NewActivePass123!', $upgradedUser->password));
        $this->assertSame($upgradedUser->id, $employee->fresh()->user_id);
    }

    public function test_user_deletion_nullifies_employee_user_id_preserving_employee(): void
    {
        $employee = $this->employee();
        $user = $this->createAccount($employee);
        $this->assertSame($user->id, $employee->fresh()->user_id);

        $user->delete();

        $this->assertNull($employee->fresh()->user_id);
        $this->assertDatabaseHas('employes', ['id' => $employee->id]);
    }

    public function test_table_configuration_has_max_10_rows_by_default(): void
    {
        $livewire = Livewire::test(ListUsers::class);
        $instance = $livewire->instance();
        $table = $instance->getTable();

        $this->assertSame(10, $table->getDefaultPaginationPageOption());
        $this->assertSame([10, 25, 50, 100], $table->getPaginationPageOptions());
    }

    public function test_table_only_displays_users_with_real_accounts(): void
    {
        $placeholderEmp = Employe::create([
            'matricule' => 'EMP-DUMMY',
            'nom' => 'Dummy',
            'prenom' => 'Staff',
            'email' => null,
            'telephone' => '670097387',
            'poste' => 'Agent',
            'date_embauche' => today(),
            'statut' => 'actif',
        ]);
        $placeholderUser = User::create([
            'name' => 'Dummy Staff',
            'nom' => 'Dummy',
            'prenom' => 'Staff',
            'email' => "personnel.{$placeholderEmp->id}@ambassadors.local",
            'statut' => 'desactive',
            'facilg_legacy_id' => "personnel-access:{$placeholderEmp->id}",
            'password' => Hash::make('secret'),
        ]);
        $placeholderEmp->update(['user_id' => $placeholderUser->id]);

        $realEmp = $this->employee();
        $realUser = $this->createAccount($realEmp);

        $visibleUserIds = UserResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($realUser->id, $visibleUserIds, 'Real staff account should be displayed in users table');
        $this->assertNotContains($placeholderUser->id, $visibleUserIds, 'Placeholder staff account should NOT be displayed in users table');
    }

    public function test_role_and_niveau_are_not_displayed_in_administration_and_inherit_from_rh(): void
    {
        $createTest = Livewire::test(CreateUser::class);
        $createTest->assertFormFieldDoesNotExist('role');
        $createTest->assertFormFieldDoesNotExist('niveau_id');

        $listTest = Livewire::test(ListUsers::class);
        $table = $listTest->instance()->getTable();
        $this->assertArrayNotHasKey('roles.name', $table->getColumns());
        $this->assertArrayNotHasKey('niveau_id', $table->getColumns());

        $role = Role::firstOrCreate(['name' => 'Comptable']);
        $employee = Employe::create([
            'matricule' => 'EMP-RH-01',
            'nom' => 'Fokou',
            'prenom' => 'Alain',
            'email' => 'alain.fokou@example.test',
            'telephone' => '670097386',
            'role_id' => $role->id,
            'poste' => 'Comptable',
            'date_embauche' => today(),
            'statut' => 'actif',
        ]);

        $user = app(StaffAccountService::class)->create([
            'employe_id' => $employee->id,
            'email' => 'alain.fokou@ambassadors.com',
            'password' => 'Pass12345!',
        ]);

        $this->assertTrue($user->hasRole('Comptable'), 'User should inherit role defined on Employe in RH');
    }

    public function test_user_creation_notification_is_standardized_success_with_activation_link_message(): void
    {
        $employee = $this->employee();
        $user = $this->createAccount($employee);

        $notification = CreateUser::creationNotification($user);

        $this->assertSame('success', $notification->getStatus());
        $this->assertSame('Utilisateur créé avec succès', $notification->getTitle());
        $this->assertSame("Le lien d'activation a été envoyé.", $notification->getBody());
        $this->assertSame(4000, $notification->getDuration());
    }

    public function test_global_notification_durations_match_erp_standards(): void
    {
        $this->assertSame(4000, Notification::make()->success()->getDuration());
        $this->assertSame(5000, Notification::make()->info()->getDuration());
        $this->assertSame(6000, Notification::make()->warning()->getDuration());
        $this->assertSame(8000, Notification::make()->danger()->getDuration());
        $this->assertSame('persistent', Notification::make()->danger()->persistent()->getDuration());
        $this->assertSame(5000, Notification::make()->getDuration());
    }

    public function test_administration_dashboard_stats_display_real_users_deleted_accounts_and_permissions(): void
    {
        $dashboard = new SocleDashboard;
        $reflector = new \ReflectionClass($dashboard);
        $method = $reflector->getMethod('getViewData');
        $method->setAccessible(true);
        $data = $method->invoke($dashboard);

        $stats = collect($data['stats']);
        $this->assertCount(3, $stats);

        $titles = $stats->pluck('title')->all();
        $this->assertSame(['Utilisateurs actifs', 'Comptes supprimés', 'Permissions'], $titles);
        $this->assertNotContains('Rôles définis', $titles);
        $this->assertNotContains('Comptes bloqués', $titles);

        $activeUsersStat = $stats->firstWhere('title', 'Utilisateurs actifs');
        $this->assertSame(
            UserResource::getEloquentQuery()->where('statut', 'actif')->count(),
            $activeUsersStat['value']
        );

        $deletedAccountsStat = $stats->firstWhere('title', 'Comptes supprimés');
        $this->assertSame(
            Activity::where('log_name', 'utilisateurs')->where('event', 'deleted')->count(),
            $deletedAccountsStat['value']
        );
        $this->assertSame('heroicon-o-trash', $deletedAccountsStat['icon']);

        $permissionsStat = $stats->firstWhere('title', 'Permissions');
        $this->assertSame(Permission::count(), $permissionsStat['value']);
        $this->assertSame('heroicon-o-key', $permissionsStat['icon']);
    }

    public function test_user_deletion_logs_to_activity_and_increments_deleted_counter(): void
    {
        $initialDeleted = Activity::where('log_name', 'utilisateurs')->where('event', 'deleted')->count();

        $employee = $this->employee();
        $user = $this->createAccount($employee);

        $user->delete();

        $afterDelete = Activity::where('log_name', 'utilisateurs')->where('event', 'deleted')->count();
        $this->assertSame($initialDeleted + 1, $afterDelete);

        $lastLog = Activity::where('log_name', 'utilisateurs')->where('event', 'deleted')->latest()->first();
        $this->assertSame('deleted', $lastLog->event);
        $this->assertSame($user->email, $lastLog->getExtraProperty('email'));

        // Placeholder account deletion should not be logged
        $placeholder = User::create([
            'name' => 'Placeholder Non Reel',
            'email' => 'personnel.placeholder@ambassadors.local',
            'statut' => 'actif',
            'password' => 'secret123',
        ]);
        $placeholder->delete();

        $afterPlaceholderDelete = Activity::where('log_name', 'utilisateurs')->where('event', 'deleted')->count();
        $this->assertSame($afterDelete, $afterPlaceholderDelete);
    }

    public function test_administration_navigation_has_habilitations_with_three_submenus(): void
    {
        $response = $this->get(ManageHabilitations::getUrl());
        $response->assertSuccessful();
        $response->assertSee('Habilitations');
        $response->assertSee('Habilitation du personnel');
        $response->assertSee('Groupes');
        $response->assertSee('Utilisateurs');

        // Check the page header and subtitle
        $response->assertSee('Matrice des habilitations dynamiques par habilitation et profil du personnel.');

        // Test Groupe permissions page
        $groupResponse = $this->get(ManageGroupePermissions::getUrl());
        $groupResponse->assertSuccessful();
        $groupResponse->assertSee('Habilitations des groupes');
        $groupResponse->assertSee('Habilitation du personnel');
        $groupResponse->assertSee('Groupes');
        $groupResponse->assertSee('Utilisateurs');

        // Test User permissions page
        $userResponse = $this->get(ManageUserPermissions::getUrl());
        $userResponse->assertSuccessful();
        $userResponse->assertSee('Habilitations des utilisateurs');
        $userResponse->assertSee('Habilitation du personnel');
        $userResponse->assertSee('Groupes');
        $userResponse->assertSee('Utilisateurs');
    }
}
