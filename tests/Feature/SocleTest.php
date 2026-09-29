<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use App\Modules\Socle\Exceptions\MotifObligatoireException;
use App\Modules\Socle\Exceptions\RoleNiveauRequisException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use App\Modules\Socle\Services\AssignationRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Fondateur']);
        Role::firstOrCreate(['name' => 'Directeur']);
        Role::firstOrCreate(['name' => 'Comptable']);
    }

    public function test_assignation_role_requiert_niveau_pour_directeur(): void
    {
        $user = User::factory()->create();
        $service = app(AssignationRoleService::class);

        $this->expectException(RoleNiveauRequisException::class);
        $service->assignerRole($user, 'Directeur', null);
    }

    public function test_assignation_role_avec_niveau_succes(): void
    {
        $niveau = Niveau::create(['nom' => 'Primaire', 'code' => 'PRIM', 'ordre' => 1]);
        $user = User::factory()->create();
        $service = app(AssignationRoleService::class);

        $service->assignerRole($user, 'Directeur', $niveau->id);

        $this->assertTrue($user->hasRole('Directeur'));
        $this->assertEquals($niveau->id, $user->fresh()->niveau_id);
    }

    public function test_numerotation_service_generer_numero_atomique(): void
    {
        FormatNumerotation::create([
            'type_document' => 'recu',
            'format' => 'REC-{{annee}}-{{seq:4}}',
            'prochain_numero' => 1,
        ]);

        $parametrageService = app(ParametrageServiceContract::class);
        $num1 = $parametrageService->genererNumero('recu', ['annee' => '2026']);
        $num2 = $parametrageService->genererNumero('recu', ['annee' => '2026']);

        $this->assertEquals('REC-2026-0001', $num1);
        $this->assertEquals('REC-2026-0002', $num2);
    }

    public function test_annee_scolaire_activation_unique(): void
    {
        $annee1 = AnneeScolaire::create([
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-06-30',
            'statut' => 'active',
        ]);

        $annee2 = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'brouillon',
        ]);

        $service = app(AnneeScolaireServiceContract::class);
        $service->activerAnnee($annee2->id);

        $this->assertEquals('cloturee', $annee1->fresh()->statut);
        $this->assertEquals('active', $annee2->fresh()->statut);
        $this->assertEquals($annee2->id, $service->getAnneeCouranteId());
    }

    public function test_audit_service_avec_motif_obligatoire(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $auditService = app(AuditServiceContract::class);

        $this->expectException(MotifObligatoireException::class);
        $auditService->enregistrerAvecMotif($user, 'Annulation paiement', '   ');
    }
}
