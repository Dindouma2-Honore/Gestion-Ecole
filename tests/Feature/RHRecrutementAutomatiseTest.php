<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Filament\Resources\CandidatureResource;
use App\Modules\RH\Filament\Resources\CandidatureResource\Pages\CreateCandidature;
use App\Modules\RH\Filament\Resources\CandidatureResource\Pages\ListCandidatures;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\CreateEmploye;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Filament\Resources\UserResource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RHRecrutementAutomatiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_embauche_cree_compte_dossier_historique_et_contrat_mixte(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::query()->where('name', 'Enseignant')->firstOrFail();

        $resultat = app(EmployeServiceContract::class)->embaucher([
            'nom' => 'Ngo', 'prenom' => 'Sarah', 'email' => 'sarah.ngo@ambassadors.test',
            'telephone' => '699000000', 'role_id' => $role->id,
            'date_embauche' => '2026-09-07',
            'responsabilite_fixe' => true, 'responsabilite_horaire' => true,
            'type_contrat' => 'CDI', 'date_debut_contrat' => '2026-09-07',
            'salaire_base' => 220000, 'taux_horaire' => 3500,
        ]);

        $employe = $resultat['employe']->fresh(['user', 'contrats', 'historiqueCarriere']);
        $this->assertMatchesRegularExpression('/^EMP-2026-\d{4}$/', $employe->matricule);
        $this->assertTrue($employe->user->must_change_password);
        $this->assertTrue($employe->user->hasRole('Enseignant'));
        $this->assertSame('Enseignant', $employe->poste);
        $this->assertSame('actif', $employe->statut);
        $this->assertSame('mixte', $employe->contrats->first()->categorie_paie);
        $this->assertSame('embauche', $employe->historiqueCarriere->first()->evenement);
    }

    public function test_recrutement_reste_dans_rh_et_creation_de_compte_est_disponible_en_administration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $candidature = Candidature::query()->create([
            'nom' => 'Manga', 'prenom' => 'Luc', 'email' => 'luc.manga@example.test',
            'poste_souhaite' => 'Comptable',
        ]);

        $this->get(CandidatureResource::getUrl())->assertSuccessful();
        Livewire::test(ListCandidatures::class)->assertCanSeeTableRecords([$candidature]);
        $this->assertTrue(UserResource::shouldRegisterNavigation());
        $this->assertTrue(UserResource::canCreate());
    }

    public function test_creation_candidature_prend_la_date_automatique_et_propose_les_roles_comme_postes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        Livewire::test(CreateCandidature::class)
            ->assertFormFieldDoesNotExist('date_entretien')
            ->fillForm([
                'nom' => 'Biloa',
                'prenom' => 'Esther',
                'email' => 'esther.biloa@example.test',
                'telephone' => '699000111',
                'poste_souhaite' => 'Enseignant',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $candidature = Candidature::query()->where('email', 'esther.biloa@example.test')->firstOrFail();

        $this->assertSame('Enseignant', $candidature->poste_souhaite);
        $this->assertNotNull($candidature->created_at);
        $this->assertSame('nouvelle', $candidature->statut);
    }

    public function test_un_personnel_historique_peut_recevoir_un_acces_sans_etre_recree(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::query()->where('name', 'Comptable')->firstOrFail();
        $employe = Employe::query()->create([
            'matricule' => 'FACILG-ACCESS-01', 'nom' => 'Nana', 'prenom' => 'Paul',
            'poste' => 'Poste FACILG à reclasser', 'date_embauche' => '2020-09-01', 'statut' => 'actif',
        ]);

        $resultat = app(EmployeServiceContract::class)->creerAcces($employe->id, 'paul.nana@ambassadors.test', $role->id);

        $this->assertNotNull($resultat['mot_de_passe_temporaire']);
        $this->assertTrue($resultat['user']->must_change_password);
        $this->assertTrue($resultat['user']->hasRole('Comptable'));
        $this->assertSame($resultat['user']->id, $employe->fresh()->user_id);
        $this->assertSame('Comptable', $employe->fresh()->poste);
    }

    public function test_un_dossier_accepte_peut_etre_revoque_avant_integration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $candidature = Candidature::query()->create([
            'nom' => 'Nana', 'prenom' => 'Alice', 'email' => 'alice.nana@example.test',
            'poste_souhaite' => 'Assistante', 'statut' => 'retenue',
        ]);

        $this->actingAs($fondateur);
        Livewire::test(ListCandidatures::class)
            ->assertTableActionVisible('rejeter', $candidature)
            ->callTableAction('rejeter', $candidature);

        $this->assertSame('rejetee', $candidature->fresh()->statut);
        $this->assertSame($fondateur->id, $candidature->fresh()->evaluee_par);
    }

    public function test_nouveau_personnel_exige_une_candidature_acceptee_et_un_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        Livewire::test(CreateEmploye::class)
            ->fillForm([
                'nom' => 'Sans', 'prenom' => 'Dossier', 'email' => 'sans.dossier@example.test',
                'date_embauche' => '2026-09-09', 'formule_paie' => 'fixe',
                'type_contrat' => 'CDI', 'date_debut_contrat' => '2026-09-09', 'salaire_base' => 150000,
            ])
            ->call('create')
            ->assertHasFormErrors(['candidature_id', 'role_id']);
    }

    public function test_creation_employe_affiche_le_recapitulatif_candidat_et_propose_son_role(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $role = Role::query()->where('name', 'Enseignant')->firstOrFail();
        $candidature = Candidature::query()->create([
            'nom' => 'Leukeu',
            'prenom' => 'Brodon',
            'email' => 'brodon.leukeu@example.test',
            'telephone' => '677637429',
            'poste_souhaite' => 'Enseignant',
            'statut' => 'retenue',
        ]);

        Livewire::test(CreateEmploye::class)
            ->fillForm(['candidature_id' => $candidature->id])
            ->assertSee('Identité du candidat')
            ->assertSee('Suivi du recrutement')
            ->assertSee('Leukeu Brodon')
            ->assertFormSet([
                'nom' => 'Leukeu',
                'prenom' => 'Brodon',
                'email' => 'brodon.leukeu@example.test',
                'telephone' => '677637429',
                'role_id' => $role->id,
            ]);
    }
}
