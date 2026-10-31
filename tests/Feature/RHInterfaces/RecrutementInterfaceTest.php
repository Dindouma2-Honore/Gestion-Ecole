<?php

declare(strict_types=1);

namespace Tests\Feature\RHInterfaces;

use App\Models\User;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Filament\Resources\CandidatureResource;
use App\Modules\RH\Filament\Resources\CandidatureResource\Pages\CreateCandidature;
use App\Modules\RH\Filament\Resources\CandidatureResource\Pages\ListCandidatures;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\CreateEmploye;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecrutementInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->adminUser = User::factory()->create(['statut' => 'actif']);
        $this->adminUser->assignRole('Fondateur');

        Role::firstOrCreate(['name' => 'Comptable', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Enseignant', 'guard_name' => 'web']);
    }

    public function test_acces_page_recrutement_selon_permission(): void
    {
        // Utilisateur non autorisé
        $unauthorizedUser = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($unauthorizedUser);
        $this->get(CandidatureResource::getUrl())->assertForbidden();

        // Utilisateur autorisé
        $this->actingAs($this->adminUser);
        $this->get(CandidatureResource::getUrl())->assertSuccessful();
    }

    public function test_creation_candidature_via_interface(): void
    {
        $this->actingAs($this->adminUser);

        PosteAdministratif::query()->create([
            'nom' => 'Comptable',
            'actif' => true,
        ]);

        Livewire::test(CreateCandidature::class)
            ->fillForm([
                'nom' => 'Kouam',
                'prenom' => 'Alain',
                'email' => 'alain.kouam@ambassadors.test',
                'telephone' => '670000001',
                'poste_souhaite' => 'Comptable',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('candidatures', [
            'nom' => 'Kouam',
            'prenom' => 'Alain',
            'email' => 'alain.kouam@ambassadors.test',
            'poste_souhaite' => 'Comptable',
            'statut' => 'nouvelle',
        ]);
    }

    public function test_validation_formulaire_candidature(): void
    {
        $this->actingAs($this->adminUser);

        Livewire::test(CreateCandidature::class)
            ->fillForm([
                'nom' => '',
                'prenom' => '',
                'email' => 'format-email-invalide',
                'poste_souhaite' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['nom', 'prenom', 'email', 'poste_souhaite']);
    }

    public function test_decision_retenir_candidature_via_interface(): void
    {
        $this->actingAs($this->adminUser);

        $candidature = Candidature::query()->create([
            'nom' => 'Mbarga',
            'prenom' => 'Joseph',
            'email' => 'joseph.mbarga@ambassadors.test',
            'poste_souhaite' => 'Comptable',
            'statut' => 'nouvelle',
        ]);

        Livewire::test(ListCandidatures::class)
            ->assertTableActionVisible('retenir', $candidature)
            ->callTableAction('retenir', $candidature)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Candidature mise à jour');

        $this->assertSame('retenue', $candidature->fresh()->statut);
        $this->assertSame($this->adminUser->id, $candidature->fresh()->evaluee_par);
    }

    public function test_decision_rejeter_candidature_via_interface(): void
    {
        $this->actingAs($this->adminUser);

        $candidature = Candidature::query()->create([
            'nom' => 'Onana',
            'prenom' => 'Claire',
            'email' => 'claire.onana@ambassadors.test',
            'poste_souhaite' => 'Comptable',
            'statut' => 'nouvelle',
        ]);

        Livewire::test(ListCandidatures::class)
            ->assertTableActionVisible('rejeter', $candidature)
            ->callTableAction('rejeter', $candidature)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Candidature mise à jour');

        $this->assertSame('rejetee', $candidature->fresh()->statut);
        $this->assertSame($this->adminUser->id, $candidature->fresh()->evaluee_par);
    }

    public function test_action_embauche_cree_employe_et_contrat(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Comptable',
            'actif' => true,
        ]);

        $candidature = Candidature::query()->create([
            'nom' => 'Fokou',
            'prenom' => 'Michel',
            'email' => 'michel.fokou@ambassadors.test',
            'telephone' => '691223344',
            'poste_souhaite' => 'Comptable',
            'statut' => 'retenue',
        ]);

        Livewire::test(ListCandidatures::class)
            ->assertTableActionVisible('embaucher', $candidature)
            ->callTableAction('embaucher', $candidature, data: [
                'candidature_id' => $candidature->id,
                'nom' => 'Fokou',
                'prenom' => 'Michel',
                'email' => 'michel.fokou@ambassadors.test',
                'telephone' => '691223344',
                'role_id' => $role->id,
                'poste_administratif_id' => $posteAdmin->id,
                'formule_paie' => 'fixe',
                'type_contrat' => 'CDI',
                'date_debut_contrat' => '2026-10-01',
                'date_embauche' => '2026-10-01',
                'salaire_base' => 200000,
                'statut' => 'actif',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Candidat embauché avec succès');

        // Vérification DB Candidature
        $candidatureFraiche = $candidature->fresh();
        $this->assertSame('embauche', $candidatureFraiche->statut);
        $this->assertNotNull($candidatureFraiche->employe_id);

        // Vérification DB Employe
        $employe = Employe::query()->find($candidatureFraiche->employe_id);
        $this->assertNotNull($employe);
        $this->assertSame('Fokou', $employe->nom);
        $this->assertSame('Michel', $employe->prenom);
        $this->assertSame($posteAdmin->id, $employe->poste_administratif_id);
        $this->assertSame('actif', $employe->statut);
        $this->assertNotNull($employe->user_id);

        // Vérification DB Contrat
        $contrat = $employe->contratPrincipal;
        $this->assertNotNull($contrat);
        $this->assertSame('CDI', $contrat->type);
        $this->assertSame('fixe', $contrat->categorie_paie);
        $this->assertSame('200000.00', number_format((float) $contrat->salaire_base, 2, '.', ''));
        $this->assertSame('actif', $contrat->statut);
    }

    public function test_candidature_rejetee_non_embauchable_dans_interface(): void
    {
        $this->actingAs($this->adminUser);

        $candidature = Candidature::query()->create([
            'nom' => 'Belinga',
            'prenom' => 'Paul',
            'email' => 'paul.belinga@ambassadors.test',
            'poste_souhaite' => 'Comptable',
            'statut' => 'rejetee',
        ]);

        Livewire::test(ListCandidatures::class)
            ->assertTableActionHidden('embaucher', $candidature);
    }

    public function test_double_embauche_bloquee_avec_notification_erreur(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Comptable',
            'actif' => true,
        ]);

        $candidature = Candidature::query()->create([
            'nom' => 'Essomba',
            'prenom' => 'Marc',
            'email' => 'marc.essomba@ambassadors.test',
            'telephone' => '692334455',
            'poste_souhaite' => 'Comptable',
            'statut' => 'retenue',
        ]);

        $component = Livewire::test(ListCandidatures::class);

        // Première embauche réussie
        $component->callTableAction('embaucher', $candidature, data: [
            'candidature_id' => $candidature->id,
            'nom' => 'Essomba',
            'prenom' => 'Marc',
            'email' => 'marc.essomba@ambassadors.test',
            'telephone' => '692334455',
            'role_id' => $role->id,
            'poste_administratif_id' => $posteAdmin->id,
            'formule_paie' => 'fixe',
            'type_contrat' => 'CDI',
            'date_debut_contrat' => '2026-10-01',
            'date_embauche' => '2026-10-01',
            'salaire_base' => 180000,
            'statut' => 'actif',
        ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Candidat embauché avec succès');

        $candidatureFraiche = $candidature->fresh();
        $this->assertSame('embauche', $candidatureFraiche->statut);

        // 1. Vérification que le bouton embaucher n'est plus visible (double embauche bloquée dans l'interface)
        $component->assertTableActionHidden('embaucher', $candidatureFraiche);

        // 2. Tentative de sélection de la candidature déjà embauchée dans CreateEmploye -> bloquée par validation
        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'candidature_id' => $candidatureFraiche->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['candidature_id']);

        // 3. Gestion gracieuse d'une erreur métier (pas d'exception brute, notification danger)
        $candidature2 = Candidature::query()->create([
            'nom' => 'Ngo',
            'prenom' => 'Anne',
            'email' => 'anne.ngo@ambassadors.test',
            'telephone' => '699887766',
            'poste_souhaite' => 'Comptable',
            'statut' => 'retenue',
        ]);

        $this->mock(EmployeServiceContract::class, function ($mock): void {
            $mock->shouldReceive('embaucher')
                ->andThrow(new \DomainException('Cette candidature n’est plus disponible pour une intégration.'));
        });

        Livewire::test(ListCandidatures::class)
            ->callTableAction('embaucher', $candidature2, data: [
                'candidature_id' => $candidature2->id,
                'nom' => 'Ngo',
                'prenom' => 'Anne',
                'email' => 'anne.ngo@ambassadors.test',
                'telephone' => '699887766',
                'role_id' => $role->id,
                'poste_administratif_id' => $posteAdmin->id,
                'formule_paie' => 'fixe',
                'type_contrat' => 'CDI',
                'date_debut_contrat' => '2026-10-01',
                'date_embauche' => '2026-10-01',
                'salaire_base' => 180000,
                'statut' => 'actif',
            ])
            ->assertNotified('Erreur lors de l’embauche');
    }

    public function test_embauche_via_page_create_employe_avec_pre_remplissage(): void
    {
        $this->actingAs($this->adminUser);

        $role = Role::where('name', 'Comptable')->firstOrFail();
        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Comptable',
            'actif' => true,
        ]);

        $candidature = Candidature::query()->create([
            'nom' => 'Abena',
            'prenom' => 'Sandrine',
            'email' => 'sandrine.abena@ambassadors.test',
            'telephone' => '693445566',
            'poste_souhaite' => 'Comptable',
            'statut' => 'retenue',
        ]);

        Livewire::withQueryParams(['candidature' => $candidature->id])
            ->test(CreateEmploye::class)
            ->assertFormSet([
                'candidature_id' => $candidature->id,
                'nom' => 'Abena',
                'prenom' => 'Sandrine',
                'email' => 'sandrine.abena@ambassadors.test',
                'telephone' => '693445566',
                'poste_administratif_id' => $posteAdmin->id,
                'role_id' => $role->id,
            ])
            ->fillForm([
                'formule_paie' => 'fixe',
                'type_contrat' => 'CDD',
                'salaire_base' => 220000,
                'date_debut_contrat' => '2026-10-01',
                'date_embauche' => '2026-10-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Intégration finalisée');

        $this->assertSame('embauche', $candidature->fresh()->statut);
        $this->assertDatabaseHas('employes', [
            'nom' => 'Abena',
            'prenom' => 'Sandrine',
            'poste_administratif_id' => $posteAdmin->id,
        ]);
    }
}
