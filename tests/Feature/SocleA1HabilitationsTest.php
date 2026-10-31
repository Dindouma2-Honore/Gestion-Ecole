<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Support\ModuleAccess;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\User;
use App\Modules\Communication\Filament\Resources\CommunicationTemplateResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Exceptions\MotifDesactivationHabilitationRequisException;
use App\Modules\Socle\Models\ActivityLog;
use App\Modules\Socle\Models\Fonctionnalite;
use App\Modules\Socle\Models\HabilitationRoleFonctionnalite;
use Database\Seeders\HabilitationsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SocleA1HabilitationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(HabilitationsSeeder::class);
    }

    public function test_catalogue_sync_does_not_grant_access_and_matrix_matches_custom_role(): void
    {
        Role::findOrCreate('Auditeur externe', 'web');
        $service = app(HabilitationServiceContract::class);
        $count = HabilitationRoleFonctionnalite::count();
        $this->seed(HabilitationsSeeder::class);
        $this->assertSame($count, HabilitationRoleFonctionnalite::count());
        $row = $service->getMatricePourRole('Auditeur externe')->firstWhere('code', 'rh.contrats');
        $this->assertFalse($row->habilitation_active);
        $this->assertFalse($service->moduleActifPourRole('Auditeur externe', 'module.rh'));
        Fonctionnalite::where('code', 'rh.contrats')->update(['actif' => false]);
        $this->seed(HabilitationsSeeder::class);
        $this->assertFalse(Fonctionnalite::where('code', 'rh.contrats')->firstOrFail()->actif);
    }

    public function test_new_feature_denial_blocks_direct_resource_access(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Directeur');
        $role = $user->roles()->firstOrFail();
        foreach (['module.communication' => true, 'communication.templates' => false] as $code => $active) {
            HabilitationRoleFonctionnalite::updateOrCreate([
                'role_id' => $role->id,
                'fonctionnalite_id' => Fonctionnalite::where('code', $code)->firstOrFail()->id,
            ], ['actif' => $active]);
        }
        $this->assertTrue(ModuleAccess::canAccessModule($user, 'Communication'));
        $this->assertFalse(ModuleAccess::canAccessComponent($user,
            CommunicationTemplateResource::class));
    }

    public function test_un_enseignant_perd_acces_et_le_composant_disparait_de_la_navigation(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $enseignant = User::factory()->create(['statut' => 'actif']);
        $enseignant->assignRole('Enseignant');

        $this->actingAs($fondateur);
        app(HabilitationServiceContract::class)->desactiverModulePourRole(
            'Enseignant',
            'module.scolarite',
            'Accès suspendu pour réorganisation',
        );

        $this->actingAs($enseignant);
        $this->assertFalse(ModuleAccess::canAccessComponent($enseignant, InscriptionResource::class));
        $this->assertFalse(ModuleAccess::canSeeModuleEntry($enseignant, 'Scolarite'));

        $request = Request::create('/admin/inscriptions', 'GET');
        $action = InscriptionResource::class.'@index';
        $route = new Route('GET', '/admin/inscriptions', [
            'uses' => $action,
            'controller' => $action,
        ]);
        $request->setRouteResolver(fn (): Route => $route);
        $request->setUserResolver(fn (): User => $enseignant);

        try {
            app(EnsureModuleAccess::class)->handle($request, fn () => response('ok'));
            $this->fail('Le middleware aurait dû refuser la route.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_le_fondateur_ne_peut_jamais_etre_bloque(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $fonctionnalite = Fonctionnalite::where('code', 'module.scolarite')->firstOrFail();
        $this->assertTrue(app(HabilitationServiceContract::class)->moduleActifPourUser($fondateur, $fonctionnalite->code));
        $this->assertTrue(ModuleAccess::canAccessComponent($fondateur, InscriptionResource::class));
    }

    public function test_une_desactivation_sans_motif_est_rejetee(): void
    {
        $this->expectException(MotifDesactivationHabilitationRequisException::class);

        app(HabilitationServiceContract::class)
            ->desactiverModulePourRole('Enseignant', 'module.scolarite', '   ');
    }

    public function test_une_desactivation_est_tracee_dans_audit(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        app(HabilitationServiceContract::class)->desactiverModulePourRole(
            'Enseignant',
            'module.scolarite',
            'Décision temporaire du Fondateur',
        );

        $habilitation = HabilitationRoleFonctionnalite::query()
            ->whereHas('role', fn ($query) => $query->where('name', 'Enseignant'))
            ->whereHas('fonctionnalite', fn ($query) => $query->where('code', 'module.scolarite'))
            ->firstOrFail();

        $this->assertFalse($habilitation->actif);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $habilitation->getMorphClass(),
            'subject_id' => $habilitation->id,
            'causer_id' => $fondateur->id,
            'motif' => 'Décision temporaire du Fondateur',
        ]);
        $this->assertSame(1, ActivityLog::forSubject($habilitation)->count());
    }
}
