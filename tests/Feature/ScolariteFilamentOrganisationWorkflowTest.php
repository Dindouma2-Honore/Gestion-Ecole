<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages\ListClasses;
use App\Modules\Scolarite\Filament\Resources\CycleResource;
use App\Modules\Scolarite\Filament\Resources\CycleResource\Pages\ListCycles;
use App\Modules\Scolarite\Filament\Resources\FiliereResource;
use App\Modules\Scolarite\Filament\Resources\FiliereResource\Pages\ListFilieres;
use App\Modules\Scolarite\Filament\Resources\NiveauResource;
use App\Modules\Scolarite\Filament\Resources\NiveauResource\Pages\EditNiveau;
use App\Modules\Scolarite\Filament\Resources\NiveauResource\Pages\ListNiveaux;
use App\Modules\Scolarite\Filament\Resources\SectionScolaireResource;
use App\Modules\Scolarite\Filament\Resources\SectionScolaireResource\Pages\ListSectionsScolaires;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Filiere;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\SectionScolaire;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ScolariteFilamentOrganisationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['statut' => 'actif']);
        $this->admin->assignRole('Fondateur');
        $this->actingAs($this->admin);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);
    }

    public function test_cycle_creation_et_modification_depuis_la_modale_filament(): void
    {
        Livewire::test(ListCycles::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit');

        Livewire::test(ListCycles::class)
            ->callAction('create', [
                'nom' => 'Primaire',
                'code' => 'PRI',
                'description' => 'CP1 à CM2',
                'ordre' => 1,
                'actif' => true,
            ]);

        $cycle = Cycle::where('code', 'PRI')->firstOrFail();
        $this->assertSame('Primaire', $cycle->nom);
        $this->assertTrue((bool) $cycle->actif);

        Livewire::test(ListCycles::class)
            ->callTableAction('edit', $cycle, [
                'nom' => 'Primaire (fondamental)',
                'code' => 'PRI',
                'description' => 'Cycle fondamental',
                'ordre' => 1,
                'actif' => true,
            ]);

        $this->assertSame('Primaire (fondamental)', $cycle->fresh()->nom);

        $this->get(CycleResource::getUrl('index'))->assertSuccessful();
        $this->get(CycleResource::getUrl('edit', ['record' => $cycle]))->assertSuccessful();
    }

    public function test_niveau_creation_et_modification_lie_au_cycle(): void
    {
        $cycle = Cycle::create(['nom' => 'Secondaire', 'code' => 'SEC', 'ordre' => 2]);

        Livewire::test(ListNiveaux::class)
            ->assertSuccessful()
            ->assertActionExists('create');

        Livewire::test(ListNiveaux::class)
            ->callAction('create', [
                'cycle_id' => $cycle->id,
                'nom' => '6ème',
                'code' => '6E',
                'ordre' => 1,
            ]);

        $niveau = Niveau::where('code', '6E')->firstOrFail();
        $this->assertSame($cycle->id, (int) $niveau->cycle_id);

        // La ressource Niveau n'expose aucune action "edit" sur la table :
        // la modification passe par la page dédiée (EditNiveau).
        Livewire::test(EditNiveau::class, ['record' => $niveau->id])
            ->fillForm([
                'cycle_id' => $cycle->id,
                'nom' => '6ème (renforcé)',
                'code' => '6E',
                'ordre' => 1,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('6ème (renforcé)', $niveau->fresh()->nom);

        $this->get(NiveauResource::getUrl('index'))->assertSuccessful();
        $this->get(NiveauResource::getUrl('edit', ['record' => $niveau]))->assertSuccessful();
    }

    public function test_filiere_et_section_scolaire_creation_et_modification(): void
    {
        Livewire::test(ListFilieres::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit');

        Livewire::test(ListFilieres::class)
            ->callAction('create', [
                'nom' => 'Scientifique',
                'code' => 'SC',
                'description' => 'Série scientifique',
                'actif' => true,
            ]);

        $filiere = Filiere::where('code', 'SC')->firstOrFail();

        Livewire::test(ListFilieres::class)
            ->callTableAction('edit', $filiere, [
                'nom' => 'Scientifique A',
                'code' => 'SC',
                'description' => 'Série scientifique A',
                'actif' => true,
            ]);

        $this->assertSame('Scientifique A', $filiere->fresh()->nom);

        Livewire::test(ListSectionsScolaires::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit');

        Livewire::test(ListSectionsScolaires::class)
            ->callAction('create', [
                'nom' => 'Section A',
                'code' => 'SEC-A',
                'description' => 'Section du matin',
                'actif' => true,
            ]);

        $section = SectionScolaire::where('code', 'SEC-A')->firstOrFail();

        Livewire::test(ListSectionsScolaires::class)
            ->callTableAction('edit', $section, [
                'nom' => 'Section A — renforcée',
                'code' => 'SEC-A',
                'description' => 'Section du matin',
                'actif' => true,
            ]);

        $this->assertSame('Section A — renforcée', $section->fresh()->nom);

        $this->get(FiliereResource::getUrl('index'))->assertSuccessful();
        $this->get(SectionScolaireResource::getUrl('index'))->assertSuccessful();
    }

    public function test_classe_creation_cree_code_effectif_et_configuration_financiere(): void
    {
        $cycle = Cycle::create(['nom' => 'Secondaire', 'code' => 'SEC', 'ordre' => 2]);
        $niveau = Niveau::create(['nom' => '6ème', 'code' => '6E', 'ordre' => 1, 'cycle_id' => $cycle->id]);

        $employe = Employe::create([
            'matricule' => 'ENS-ORG',
            'nom' => 'Prof',
            'prenom' => 'Alain',
            'poste' => 'Enseignant',
            'date_embauche' => today(),
            'statut' => 'actif',
        ]);
        $enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'specialite' => 'Maths',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 18,
        ]);

        Livewire::test(ListClasses::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view');

        Livewire::test(ListClasses::class)
            ->callAction('create', [
                'nom' => '6ème A',
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $this->annee->id,
                'capacite_max' => 30,
                'professeur_principal_id' => $enseignant->id,
                'statut' => 'active',
                'frais_inscription' => 15000,
                'tranche_1' => 25000,
                'tranche_2' => 25000,
                'tranche_3' => 25000,
                'tranche_4' => 25000,
                'tranche_5' => 25000,
            ]);

        $classe = Classe::where('nom', '6ème A')->firstOrFail();
        $this->assertSame($niveau->id, (int) $classe->niveau_id);
        $this->assertSame($this->annee->id, (int) $classe->annee_scolaire_id);
        $this->assertSame($enseignant->id, (int) $classe->professeur_principal_id);
        $this->assertNotEmpty($classe->code);

        // La création de la classe doit déclencher la configuration
        // financière (ConfigurationFraisClasse + tranches) côté Scolarité.
        $configuration = DB::table('configurations_frais_classe')
            ->where('classe_id', $classe->id)
            ->where('annee_scolaire_id', $this->annee->id)
            ->first();
        $this->assertNotNull($configuration, 'La configuration de frais de la classe est absente.');
        $this->assertSame(15000.0, (float) $configuration->frais_inscription);
        $this->assertSame(125000.0, (float) $configuration->montant_total);
        $this->assertSame(5, DB::table('tranches_frais_classe')
            ->where('configuration_frais_classe_id', $configuration->id)
            ->count());

        Livewire::test(ListClasses::class)
            ->callTableAction('edit', $classe, [
                'nom' => '6ème A (bis)',
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $this->annee->id,
                'capacite_max' => 35,
                'professeur_principal_id' => $enseignant->id,
                'statut' => 'active',
                'frais_inscription' => 15000,
                'tranche_1' => 30000,
                'tranche_2' => 30000,
                'tranche_3' => 30000,
                'tranche_4' => 30000,
                'tranche_5' => 30000,
            ]);

        $classeFresh = $classe->fresh();
        $this->assertSame('6ème A (bis)', $classeFresh->nom);
        $this->assertSame(35, (int) $classeFresh->capacite_max);

        $configurationFresh = DB::table('configurations_frais_classe')
            ->where('classe_id', $classe->id)
            ->first();
        $this->assertSame(150000.0, (float) $configurationFresh->montant_total);

        $this->get(ClasseResource::getUrl('index'))->assertSuccessful();
        $this->get(ClasseResource::getUrl('view', ['record' => $classe]))->assertSuccessful();
        $this->get(ClasseResource::getUrl('edit', ['record' => $classe]))->assertSuccessful();
    }

    public function test_chaine_organisation_complete_cycle_vers_classe(): void
    {
        $cycle = Cycle::create(['nom' => 'Primaire', 'code' => 'PRI', 'ordre' => 1]);
        $niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 5, 'cycle_id' => $cycle->id]);
        $filiere = Filiere::create(['nom' => 'Général', 'code' => 'GEN', 'actif' => true]);
        $section = SectionScolaire::create(['nom' => 'Section Sud', 'code' => 'SUD', 'actif' => true]);

        $employe = Employe::create([
            'matricule' => 'ENS-CHAIN',
            'nom' => 'Mballa',
            'prenom' => 'Éric',
            'poste' => 'Enseignant',
            'date_embauche' => today(),
            'statut' => 'actif',
        ]);
        $enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'specialite' => 'Généraliste',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 20,
        ]);

        Livewire::test(ListClasses::class)
            ->callAction('create', [
                'nom' => 'CM2 A',
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $this->annee->id,
                'capacite_max' => 40,
                'filiere_id' => $filiere->id,
                'section_id' => $section->id,
                'professeur_principal_id' => $enseignant->id,
                'statut' => 'active',
                'frais_inscription' => 15000,
                'tranche_1' => 20000,
                'tranche_2' => 20000,
                'tranche_3' => 20000,
                'tranche_4' => 20000,
                'tranche_5' => 20000,
            ]);

        $classe = Classe::where('nom', 'CM2 A')->firstOrFail();
        $this->assertSame($cycle->id, (int) $niveau->fresh()->cycle_id);
        $this->assertSame($niveau->id, (int) $classe->niveau_id);
        $this->assertSame($filiere->id, (int) $classe->filiere_id);
        $this->assertSame($section->id, (int) $classe->section_id);
        $this->assertNotNull($classe->code);
    }
}
