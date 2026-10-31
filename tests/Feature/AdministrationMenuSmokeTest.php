<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Logistique\Filament\Resources\SalleResource;
use App\Modules\Pedagogie\Filament\Resources\CreneauHoraireResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Resources\CycleResource;
use App\Modules\Scolarite\Filament\Resources\FiliereResource;
use App\Modules\Scolarite\Filament\Resources\NiveauResource;
use App\Modules\Scolarite\Filament\Resources\SectionScolaireResource;
use App\Modules\Socle\Filament\Pages\ManageEtablissementSettings;
use App\Modules\Socle\Filament\Pages\ManageGroupePermissions;
use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use App\Modules\Socle\Filament\Pages\ManageSystemSettings;
use App\Modules\Socle\Filament\Pages\ManageUserPermissions;
use App\Modules\Socle\Filament\Resources\ActivityLogResource;
use App\Modules\Socle\Filament\Resources\AnneeScolaireResource;
use App\Modules\Socle\Filament\Resources\GroupeResource;
use App\Modules\Socle\Filament\Resources\JourFerieResource;
use App\Modules\Socle\Filament\Resources\PeriodeResource;
use App\Modules\Socle\Filament\Resources\UserResource;
use App\Modules\Socle\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrationMenuSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_can_open_every_visible_administration_menu(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');

        $menus = [
            'Utilisateurs' => UserResource::getUrl(),
            'Habilitations par rôle' => ManageHabilitations::getUrl(),
            'Habilitations individuelles' => ManageUserPermissions::getUrl(),
            'Groupes' => GroupeResource::getUrl(),
            'Habilitations par groupe' => ManageGroupePermissions::getUrl(),
            'Informations établissement' => ManageEtablissementSettings::getUrl(),
            'Cycles' => CycleResource::getUrl(),
            'Niveaux' => NiveauResource::getUrl(),
            'Sections' => SectionScolaireResource::getUrl(),
            'Filières' => FiliereResource::getUrl(),
            'Classes' => ClasseResource::getUrl(),
            'Matières' => MatiereResource::getUrl(),
            'Coefficients' => OffrePedagogiqueResource::getUrl(),
            'Salles' => SalleResource::getUrl(),
            'Créneaux horaires' => CreneauHoraireResource::getUrl(),
            'Jours fériés' => JourFerieResource::getUrl(),
            'Années scolaires' => AnneeScolaireResource::getUrl(),
            'Périodes' => PeriodeResource::getUrl(),
            'Audit et sécurité' => ActivityLogResource::getUrl(),
            'Configuration système' => ManageSystemSettings::getUrl(),
        ];

        foreach ($menus as $menu => $url) {
            $this->actingAs($fondateur)->get($url)->assertSuccessful("Le menu {$menu} ne s’ouvre pas correctement.");
        }
    }

    public function test_all_facilg_users_are_available_in_administration_even_without_employee_link(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');
        $facilg = User::factory()->create([
            'name' => 'Utilisateur historique FACILG',
            'statut' => 'actif',
            'facilg_legacy_id' => 'utilisateur:TEST001',
        ]);

        $this->actingAs($fondateur);
        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$facilg]);
        $this->get(ManageUserPermissions::getUrl())->assertSuccessful()->assertSee('Utilisateur historique FACILG');
    }

    public function test_all_linked_staff_are_available_even_when_their_account_is_disabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif', 'must_change_password' => false]);
        $fondateur->assignRole('Fondateur');
        $personnel = User::factory()->create([
            'name' => 'Personnel sans accès',
            'statut' => 'desactive',
        ]);
        DB::table('employes')->insert([
            'user_id' => $personnel->id,
            'matricule' => 'PERS-TEST-001',
            'nom' => 'Personnel',
            'prenom' => 'Sans accès',
            'poste' => 'Assistant',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($fondateur);
        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$personnel])
            ->assertTableColumnDoesNotExist('facilg_legacy_id');
        $this->get(ManageUserPermissions::getUrl())
            ->assertSuccessful()
            ->assertSee('Personnel sans accès');
    }
}
