<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\Socle\Models\FormatNumerotation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrationDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_legacy_administration_dashboard_redirects_to_users_and_access(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get('/admin/socle')
            ->assertRedirect('/admin/users');
    }

    public function test_dashboard_renders_without_error_once_every_submodule_has_real_data(): void
    {
        $this->withoutExceptionHandling();
        Storage::fake('documents');
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);

        app(DocumentServiceContract::class)->attacher(
            $user,
            UploadedFile::fake()->create('contrat.pdf', 50, 'application/pdf'),
            'contrat',
            'restreint',
            now()->addDays(10),
        );

        FormatNumerotation::create([
            'type_document' => 'courrier',
            'format' => 'COUR-{{annee}}-{{seq:3}}',
            'prochain_numero' => 1,
        ]);
        $courrier = app(CourrierServiceContract::class)->enregistrerCourrierEntrant([
            'objet' => 'Demande',
            'expediteur' => 'M. Dupont',
            'destinataire' => 'Secrétariat',
            'date_limite_reponse' => now()->subDay(),
        ]);

        $tache = app(TacheServiceContract::class)->creerTache('Suivi', $responsable->id, now()->subDay());
        $tache->changerStatut('en_cours', $user);
        app(TacheServiceContract::class)->demarrerCircuitValidation($tache->id, [$responsable->id]);

        $reunion = app(ReunionServiceContract::class)->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );
        app(ReunionServiceContract::class)->ajouterDecision(
            $reunion->id,
            'Décision test',
            $responsable->id,
            now()->addDays(5),
        );

        $this->assertNotNull($courrier->id);
        $this->get('/admin/socle')->assertRedirect('/admin/users');
    }

    public function test_administration_sidebar_exposes_only_the_six_governance_sections(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get('/admin/users')
            ->assertSuccessful()
            ->assertSee('ADMINISTRATION')
            ->assertSee('Utilisateurs &amp; habilitations', false)
            ->assertSee('Structure de l’établissement')
            ->assertSee('Années &amp; périodes', false)
            ->assertSee('Modèles de documents')
            ->assertSee('Workflows &amp; validations', false)
            ->assertSee('Audit &amp; sécurité', false)
            ->assertSee('Habilitations dynamiques')
            ->assertDontSee('Permissions des groupes');
    }
}
