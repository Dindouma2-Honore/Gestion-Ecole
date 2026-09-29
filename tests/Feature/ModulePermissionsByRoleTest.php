<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Support\ModuleAccess;
use App\Filament\Support\ModuleCatalog;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use App\Modules\RH\Filament\Resources\EmployeResource;
use App\Modules\RH\Filament\Resources\BulletinPaieResource;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Filament\Pages\ManageHabilitations;
use App\Modules\Socle\Models\Fonctionnalite;
use Database\Seeders\HabilitationsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ModulePermissionsByRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(HabilitationsSeeder::class);
    }

    public function test_administrateur_peut_acceder_a_la_page_des_habilitations_et_voir_les_roles(): void
    {
        Role::firstOrCreate(['name' => 'Admin']);
        $admin = User::factory()->create(['statut' => 'actif']);
        $admin->assignRole('Admin');

        $this->actingAs($admin);
        $this->assertTrue(ManageHabilitations::canAccess());

        $page = new ManageHabilitations;
        $roles = $page->getRoles();

        $this->assertContains('Directeur', $roles);
        $this->assertContains('Enseignant', $roles);
        $this->assertContains('Comptable', $roles);
    }

    public function test_desactiver_un_module_pour_un_role_masque_le_module_sur_accueil_et_bloque_acces(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $directeur = User::factory()->create(['statut' => 'actif']);
        $directeur->assignRole('Directeur');

        // Au départ, le Directeur voit le module RH sur la page d'accueil et accède aux composants
        $this->actingAs($directeur);
        $visibleModulesInit = collect(ModuleCatalog::visibleFor($directeur))->pluck('module');
        $this->assertContains('RH', $visibleModulesInit);
        $this->assertTrue(ModuleAccess::canAccessComponent($directeur, EmployeResource::class));

        // Le Fondateur/Admin désactive le module RH pour le rôle Directeur
        $this->actingAs($fondateur);
        app(HabilitationServiceContract::class)->desactiverModulePourRole(
            'Directeur',
            'module.rh',
            'Désactivation du module RH pour réorganisation',
        );

        // Désormais, le Directeur ne verra plus le module RH sur l'accueil
        $this->actingAs($directeur);
        $visibleModulesApres = collect(ModuleCatalog::visibleFor($directeur))->pluck('module');
        $this->assertNotContains('RH', $visibleModulesApres);
        $this->assertFalse(ModuleAccess::canAccessComponent($directeur, EmployeResource::class));

        // Et une tentative d'accès direct par URL renvoie une 403 via le middleware EnsureModuleAccess
        $request = Request::create('/admin/employes', 'GET');
        $action = EmployeResource::class.'@index';
        $route = new Route('GET', '/admin/employes', [
            'uses' => $action,
            'controller' => $action,
        ]);
        $request->setRouteResolver(fn (): Route => $route);
        $request->setUserResolver(fn (): User => $directeur);

        try {
            app(EnsureModuleAccess::class)->handle($request, fn () => response('ok'));
            $this->fail('Le middleware aurait dû lever une exception HTTP 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_desactiver_une_fonctionnalite_granulaire_bloque_uniquement_ce_composant(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');

        // Désactiver uniquement 'rh.paie' pour le Comptable
        $this->actingAs($fondateur);
        app(HabilitationServiceContract::class)->desactiverModulePourRole(
            'Comptable',
            'rh.paie',
            'Restriction temporaire sur la paie',
        );

        // Le Comptable conserve l'accès au module RH global et aux employés, mais pas à la paie
        $this->actingAs($comptable);
        $this->assertTrue(ModuleAccess::canSeeModuleEntry($comptable, 'RH'));
        $this->assertTrue(ModuleAccess::canAccessComponent($comptable, EmployeResource::class));
        $this->assertFalse(ModuleAccess::canAccessComponent($comptable, BulletinPaieResource::class));
    }

    public function test_reactiver_un_module_restaure_les_acces(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $enseignant = User::factory()->create(['statut' => 'actif']);
        $enseignant->assignRole('Enseignant');

        $this->actingAs($fondateur);
        app(HabilitationServiceContract::class)->desactiverModulePourRole(
            'Enseignant',
            'module.pedagogie',
            'Suspension temporaire',
        );

        $this->actingAs($enseignant);
        $this->assertFalse(ModuleAccess::canSeeModuleEntry($enseignant, 'Pedagogie'));

        // Réactivation
        $this->actingAs($fondateur);
        app(HabilitationServiceContract::class)->activerModulePourRole('Enseignant', 'module.pedagogie');

        $this->actingAs($enseignant);
        $this->assertTrue(ModuleAccess::canSeeModuleEntry($enseignant, 'Pedagogie'));
    }

    public function test_nouveau_role_cree_est_administrable_dans_les_habilitations(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        Role::create(['name' => 'Secretariat', 'guard_name' => 'web']);

        $secretariatUser = User::factory()->create(['statut' => 'actif']);
        $secretariatUser->assignRole('Secretariat');

        // L'administrateur active le module Socle pour le nouveau rôle Secretariat
        $this->actingAs($fondateur);
        app(HabilitationServiceContract::class)->activerModulePourRole('Secretariat', 'module.socle');

        $this->actingAs($secretariatUser);
        $this->assertTrue(ModuleAccess::canSeeModuleEntry($secretariatUser, 'Socle'));

        // L'admin désactive l'accès au Socle pour le rôle Secretariat
        $this->actingAs($fondateur);
        app(HabilitationServiceContract::class)->desactiverModulePourRole(
            'Secretariat',
            'module.socle',
            'Désactivation du Socle pour le Secrétariat',
        );

        $this->actingAs($secretariatUser);
        $this->assertFalse(ModuleAccess::canSeeModuleEntry($secretariatUser, 'Socle'));
    }
}
