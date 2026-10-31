<?php

declare(strict_types=1);

namespace Tests\Feature\RHInterfaces;

use App\Models\User;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Exceptions\ContratChevauchementException;
use App\Modules\RH\Filament\Resources\ContratResource;
use App\Modules\RH\Filament\Resources\ContratResource\Pages\CreateContrat;
use App\Modules\RH\Filament\Resources\ContratResource\Pages\EditContrat;
use App\Modules\RH\Filament\Resources\ContratResource\Pages\ListContrats;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContratInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateurUser;

    private User $comptableUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->fondateurUser = User::factory()->create(['statut' => 'actif']);
        $this->fondateurUser->assignRole('Fondateur');

        Permission::firstOrCreate(['name' => 'module.rh']);
        Role::firstOrCreate(['name' => 'Comptable', 'guard_name' => 'web']);
        $this->comptableUser = User::factory()->create(['statut' => 'actif']);
        $this->comptableUser->assignRole('Comptable');
        $this->comptableUser->givePermissionTo('module.rh');
    }

    public function test_acces_page_contrats_selon_permission(): void
    {
        // 1. Utilisateur non habilité -> 403
        $unauthorized = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($unauthorized);
        $this->get(ContratResource::getUrl())->assertForbidden();

        // 2. Utilisateur Comptable -> 200
        $this->actingAs($this->comptableUser);
        $this->get(ContratResource::getUrl())->assertSuccessful();

        // 3. Utilisateur Fondateur -> 200
        $this->actingAs($this->fondateurUser);
        $this->get(ContratResource::getUrl())->assertSuccessful();
    }

    public function test_liste_contrats_recherche_et_filtres(): void
    {
        $this->actingAs($this->fondateurUser);

        $emp1 = Employe::query()->create([
            'matricule' => 'EMP-CT-01',
            'nom' => 'Mbassi',
            'prenom' => 'Alain',
            'poste' => 'Comptable',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
        ]);

        $emp2 = Employe::query()->create([
            'matricule' => 'EMP-CT-02',
            'nom' => 'Bikoro',
            'prenom' => 'Hélène',
            'poste' => 'Secrétaire',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
        ]);

        $contrat1 = Contrat::query()->create([
            'employe_id' => $emp1->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 300000,
            'date_debut' => '2026-01-01',
            'statut' => 'actif',
        ]);

        $contrat2 = Contrat::query()->create([
            'employe_id' => $emp2->id,
            'type' => 'CDD',
            'categorie_paie' => 'horaire',
            'salaire_base' => 0,
            'taux_horaire' => 4500,
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'statut' => 'suspendu',
        ]);

        Livewire::test(ListContrats::class)
            ->assertCanSeeTableRecords([$contrat1, $contrat2])
            ->searchTable('Mbassi')
            ->assertCanSeeTableRecords([$contrat1])
            ->assertCanNotSeeTableRecords([$contrat2])
            ->searchTable('')
            ->filterTable('statut', 'suspendu')
            ->assertCanSeeTableRecords([$contrat2])
            ->assertCanNotSeeTableRecords([$contrat1])
            ->filterTable('statut', null)
            ->filterTable('type', 'CDI')
            ->assertCanSeeTableRecords([$contrat1])
            ->assertCanNotSeeTableRecords([$contrat2]);
    }

    public function test_creation_contrat_cdi_fixe_depuis_interface(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-CREAT-01',
            'nom' => 'Kamga',
            'prenom' => 'Denis',
            'poste' => 'Gestionnaire',
            'date_embauche' => '2026-02-01',
            'statut' => 'actif',
        ]);

        Livewire::test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $employe->id,
                'type' => 'CDI',
                'categorie_paie' => 'fixe',
                'salaire_base' => 280000,
                'date_debut' => '2026-02-01',
                'periode_essai_fin' => '2026-05-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 280000,
            'statut' => 'actif',
        ]);

        $nouveauContrat = Contrat::where('employe_id', $employe->id)->first();
        $this->assertNotNull($nouveauContrat);
        $this->assertNull($nouveauContrat->date_fin);
        $this->assertSame('2026-05-01', $nouveauContrat->periode_essai_fin->toDateString());
        $this->assertSame($nouveauContrat->id, $employe->fresh()->contrat_id);
    }

    public function test_creation_contrat_cdd_horaire_et_mixte(): void
    {
        $this->actingAs($this->fondateurUser);

        $emp1 = Employe::query()->create([
            'matricule' => 'EMP-HOR-01',
            'nom' => 'Owona',
            'prenom' => 'Marc',
            'poste' => 'Enseignant Vacataire',
            'date_embauche' => '2026-03-01',
            'statut' => 'actif',
        ]);

        $emp2 = Employe::query()->create([
            'matricule' => 'EMP-MIX-01',
            'nom' => 'Bilounga',
            'prenom' => 'Therese',
            'poste' => 'Conseillère Pédagogique',
            'date_embauche' => '2026-03-01',
            'statut' => 'actif',
        ]);

        // Formule horaire
        Livewire::test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $emp1->id,
                'type' => 'Prestataire',
                'categorie_paie' => 'horaire',
                'taux_horaire' => 6000,
                'date_debut' => '2026-03-01',
                'date_fin' => '2026-06-30',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $emp1->id,
            'type' => 'Prestataire',
            'categorie_paie' => 'horaire',
            'taux_horaire' => 6000,
            'statut' => 'actif',
        ]);

        // Formule mixte
        Livewire::test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $emp2->id,
                'type' => 'CDD',
                'categorie_paie' => 'mixte',
                'salaire_base' => 150000,
                'taux_horaire' => 3500,
                'date_debut' => '2026-03-01',
                'date_fin' => '2026-12-31',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contrats', [
            'employe_id' => $emp2->id,
            'type' => 'CDD',
            'categorie_paie' => 'mixte',
            'salaire_base' => 150000,
            'taux_horaire' => 3500,
            'statut' => 'actif',
        ]);
    }

    public function test_renouvellement_contrat_expire_ancien_contrat_et_lie_nouveau(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-REN-01',
            'nom' => 'Ndem',
            'prenom' => 'Samuel',
            'poste' => 'Technicien Support',
            'date_embauche' => '2025-09-01',
            'statut' => 'actif',
        ]);

        $premierContrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDD',
            'categorie_paie' => 'fixe',
            'salaire_base' => 180000,
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-06-30',
            'statut' => 'actif',
        ]);
        $employe->update(['contrat_id' => $premierContrat->id]);

        // Renouvellement via création d'un nouveau contrat à partir du 2026-07-01
        Livewire::test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $employe->id,
                'type' => 'CDI',
                'categorie_paie' => 'fixe',
                'salaire_base' => 220000,
                'date_debut' => '2026-07-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // L'ancien contrat est passé à expiré avec date_fin au jour d'avant
        $premierContratFraiche = $premierContrat->fresh();
        $this->assertSame('expire', $premierContratFraiche->statut);
        $this->assertSame('2026-06-30', $premierContratFraiche->date_fin->toDateString());

        // Le nouveau contrat est actif et lié à l'employé
        $nouveauContrat = Contrat::where('employe_id', $employe->id)->where('type', 'CDI')->first();
        $this->assertNotNull($nouveauContrat);
        $this->assertSame('actif', $nouveauContrat->statut);
        $this->assertSame($nouveauContrat->id, $employe->fresh()->contrat_id);
    }

    public function test_modification_contrat_ne_modifie_pas_statut_employe_ni_user(): void
    {
        $this->actingAs($this->fondateurUser);

        $user = User::factory()->create(['statut' => 'actif']);
        $employe = Employe::query()->create([
            'matricule' => 'EMP-INDEP-01',
            'nom' => 'Manga',
            'prenom' => 'Victor',
            'poste' => 'Chauffeur',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
            'user_id' => $user->id,
        ]);

        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDD',
            'categorie_paie' => 'fixe',
            'salaire_base' => 160000,
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'statut' => 'actif',
        ]);
        $employe->update(['contrat_id' => $contrat->id]);

        Livewire::test(EditContrat::class, ['record' => $contrat->getRouteKey()])
            ->fillForm([
                'salaire_base' => 190000,
                'statut' => 'suspendu',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('190000.00', (string) $contrat->fresh()->salaire_base);
        $this->assertSame('suspendu', $contrat->fresh()->statut);

        // Employé et User restent strictement inchangés
        $this->assertSame('actif', $employe->fresh()->statut);
        $this->assertSame('actif', $user->fresh()->statut);
    }

    public function test_ajout_fonction_remuneree_lors_de_la_modification(): void
    {
        $this->actingAs($this->fondateurUser);

        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Responsable Sécurité',
            'actif' => true,
        ]);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-FONC-01',
            'nom' => 'Talla',
            'prenom' => 'Benoît',
            'poste' => 'Agent Général',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
        ]);

        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 200000,
            'date_debut' => '2026-01-01',
            'statut' => 'actif',
        ]);
        $employe->update(['contrat_id' => $contrat->id]);

        Livewire::test(EditContrat::class, ['record' => $contrat->getRouteKey()])
            ->fillForm([
                'fonction_id' => $posteAdmin->id,
                'fonction_mode_remuneration' => 'salaire_base',
                'fonction_montant' => 50000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('contrat_fonction_remunerations', [
            'contrat_id' => $contrat->id,
            'poste_administratif_id' => $posteAdmin->id,
            'mode_remuneration' => 'salaire_base',
            'montant' => 50000,
        ]);

        $this->assertDatabaseHas('personnel_postes_historique', [
            'employe_id' => $employe->id,
            'poste_administratif_id' => $posteAdmin->id,
            'motif' => 'Fonction ajoutée lors du renouvellement du contrat',
        ]);
    }

    public function test_actions_contractuelles_suspension_reactivation_resiliation(): void
    {
        $this->actingAs($this->fondateurUser);

        $user = User::factory()->create(['statut' => 'actif']);
        $employe = Employe::query()->create([
            'matricule' => 'EMP-ACT-01',
            'nom' => 'Kuate',
            'prenom' => 'Jules',
            'poste' => 'Magasinier',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
            'user_id' => $user->id,
        ]);

        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 175000,
            'date_debut' => '2026-01-01',
            'statut' => 'actif',
        ]);
        $employe->update(['contrat_id' => $contrat->id]);

        // 1. Suspension via table action
        Livewire::test(ListContrats::class)
            ->callTableAction('suspendre', $contrat)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Contrat suspendu');

        $this->assertSame('suspendu', $contrat->fresh()->statut);
        $this->assertSame('actif', $employe->fresh()->statut);
        $this->assertSame('actif', $user->fresh()->statut);

        // 2. Réactivation via table action
        Livewire::test(ListContrats::class)
            ->callTableAction('reactiver', $contrat)
            ->assertHasNoTableActionErrors()
            ->assertNotified('Contrat réactivé');

        $this->assertSame('actif', $contrat->fresh()->statut);

        // 3. Résiliation via table action avec date d'effet et motif
        Livewire::test(ListContrats::class)
            ->callTableAction('resilier', $contrat, data: [
                'date_effet' => '2026-06-30',
                'motif' => 'Démission notifiée et acceptée',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Contrat résilié avec succès');

        $contratResilie = $contrat->fresh();
        $this->assertSame('resilie', $contratResilie->statut);
        $this->assertSame('2026-06-30', $contratResilie->date_fin->toDateString());

        // Absence d'impact sauvage sur l'employé ou l'utilisateur
        $this->assertSame('actif', $employe->fresh()->statut);
        $this->assertSame('actif', $user->fresh()->statut);
    }

    public function test_gestion_erreurs_ui_sans_exception_brute(): void
    {
        $this->actingAs($this->fondateurUser);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-ERR-01',
            'nom' => 'Fotso',
            'prenom' => 'Pascal',
            'poste' => 'Assistant',
            'date_embauche' => '2026-01-01',
            'statut' => 'actif',
        ]);

        // 1. Date de fin antérieure à la date de début -> erreur de validation
        Livewire::test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $employe->id,
                'type' => 'CDD',
                'categorie_paie' => 'fixe',
                'salaire_base' => 150000,
                'date_debut' => '2026-06-01',
                'date_fin' => '2026-05-01', // fin < début
            ])
            ->call('create')
            ->assertHasFormErrors(['date_fin']);

        // 2. Salaire négatif -> erreur de validation
        Livewire::test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $employe->id,
                'type' => 'CDI',
                'categorie_paie' => 'fixe',
                'salaire_base' => -5000,
                'date_debut' => '2026-06-01',
            ])
            ->call('create')
            ->assertHasFormErrors(['salaire_base']);

        // 3. Exception métier (ex. chevauchement) interceptée gracieusement avec notification danger
        $this->mock(ContratServiceInterface::class, function ($mock) use ($employe): void {
            $mock->shouldReceive('creerContrat')
                ->andThrow(new ContratChevauchementException($employe->id));
        });

        Livewire::test(CreateContrat::class)
            ->fillForm([
                'employe_id' => $employe->id,
                'type' => 'CDI',
                'categorie_paie' => 'fixe',
                'salaire_base' => 200000,
                'date_debut' => '2026-06-01',
            ])
            ->call('create')
            ->assertNotified('Erreur lors de la création du contrat');
    }
}
