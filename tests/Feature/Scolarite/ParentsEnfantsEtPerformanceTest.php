<?php

declare(strict_types=1);

namespace Tests\Feature\Scolarite;

use App\Models\User;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource\Pages\ListParentTuteurs;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource\Pages\ViewParentTuteur;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\DocumentNumberingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\ActionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ParentsEnfantsEtPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateur;

    private AnneeScolaire $annee;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            DocumentNumberingSeeder::class,
        ]);

        $this->fondateur = User::factory()->create(['name' => 'Fondateur Test']);
        $this->fondateur->assignRole('Fondateur');

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);

        $cycle = Cycle::create(['code' => 'PRI', 'nom' => 'Primaire', 'ordre' => 1]);
        $niveau = Niveau::create(['code' => 'CP', 'nom' => 'CP', 'cycle_id' => $cycle->id, 'ordre' => 1]);

        $this->classe = Classe::create([
            'nom' => 'CP A Test',
            'code' => 'CPA-T',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'ouverte',
        ]);
    }

    public function test_inscriptions_list_loads_fast_and_with_few_queries(): void
    {
        $this->actingAs($this->fondateur);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = microtime(true);
        $test = Livewire::test(ListInscriptions::class);
        $duration = microtime(true) - $start;

        $queries = count(DB::getQueryLog());

        $test->assertSuccessful();

        // Le chargement doit être rapide et le nombre de requêtes réduit (< 70 au lieu de 636)
        $this->assertLessThan(2.0, $duration, "ListInscriptions a pris trop de temps : {$duration}s");
        $this->assertLessThan(70, $queries, "Trop de requêtes SQL : {$queries} (doit être < 70)");
    }

    public function test_parent_tuteur_table_consistently_renders_three_action_buttons(): void
    {
        $this->actingAs($this->fondateur);

        $test = Livewire::test(ListParentTuteurs::class);
        $test->assertSuccessful();

        $table = $test->instance()->getTable();
        $actions = $table->getActions();

        // Les actions sont regroupées dans un ActionGroup (menu 3 points)
        $this->assertCount(1, $actions, 'Parents & Tuteurs doit avoir 1 ActionGroup (menu 3 points)');

        $group = $actions[0];
        $this->assertInstanceOf(ActionGroup::class, $group);

        $groupActions = $group->getActions();
        $this->assertCount(3, $groupActions, 'Le groupe doit contenir exactement 3 actions');

        $actionNames = collect($groupActions)->map(fn ($a) => $a->getName())->all();
        $this->assertContains('view', $actionNames);
        $this->assertContains('edit', $actionNames);
        $this->assertContains('delete', $actionNames);
    }

    public function test_students_with_same_parent_name_share_the_same_parent(): void
    {
        $this->actingAs($this->fondateur);

        $eleve1 = Eleve::create([
            'matricule_permanent' => 'MAT-TEST-DONGMO-01',
            'nom' => 'Dongmo',
            'prenom' => 'Junior',
            'sexe' => 'M',
            'nom_tuteur' => 'DONGMO JEAN PIERRE',
            'telephone_tuteur' => '677001122',
            'statut' => 'admis',
        ]);
        $parent1 = $eleve1->synchroniserParentTuteur();

        $this->assertNotNull($parent1);
        $this->assertEquals('DONGMO JEAN PIERRE', $parent1->nom);

        $eleve2 = Eleve::create([
            'matricule_permanent' => 'MAT-TEST-DONGMO-02',
            'nom' => 'Dongmo',
            'prenom' => 'Clarisse',
            'sexe' => 'F',
            'nom_tuteur' => 'DONGMO JEAN PIERRE',
            'telephone_tuteur' => '677001122',
            'statut' => 'admis',
        ]);
        $parent2 = $eleve2->synchroniserParentTuteur();

        $this->assertNotNull($parent2);
        // Les deux élèves doivent partager le MÊME parent
        $this->assertEquals($parent1->id, $parent2->id, 'Les deux enfants du même parent doivent partager un seul enregistrement ParentTuteur');

        // Un seul enregistrement pour ce nom
        $count = ParentTuteur::where('nom', 'DONGMO JEAN PIERRE')->count();
        $this->assertEquals(1, $count, 'Il ne doit y avoir qu\'un seul ParentTuteur pour DONGMO JEAN PIERRE');

        // Le parent doit avoir les 2 enfants
        $this->assertCount(2, $parent1->fresh()->eleves);
    }

    public function test_parent_view_links_directly_to_student_inscription_file(): void
    {
        $this->actingAs($this->fondateur);

        $parent = ParentTuteur::create([
            'nom' => 'NANA KOUAM',
            'prenom' => 'Alain',
            'telephone' => '699887766',
            'email' => 'nana.kouam@test.com',
            'profession' => 'Ingénieur',
            'portail_actif' => true,
        ]);

        $eleve = Eleve::create([
            'matricule_permanent' => 'MAT-TEST-NANA-01',
            'nom' => 'Nana',
            'prenom' => 'Boris',
            'sexe' => 'M',
            'statut' => 'inscrit',
        ]);

        $parent->eleves()->attach($eleve->id, [
            'lien' => 'pere',
            'responsable_legal' => true,
            'responsable_paiement' => true,
            'autorise_recuperation' => true,
        ]);

        $inscription = Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'date_inscription' => now()->toDateString(),
            'statut' => 'active',
            'type' => 'reinscription',
        ]);

        $expectedFicheUrl = InscriptionResource::getUrl('view', ['record' => $inscription->id]);

        $test = Livewire::test(ViewParentTuteur::class, ['record' => $parent->id]);
        $test->assertSuccessful();

        $html = $test->html();
        $this->assertStringContainsString($expectedFicheUrl, $html, 'La vue parent doit contenir un lien vers la fiche inscription de l\'élève');
        $this->assertStringContainsString('Fiche élève', $html);
        $this->assertStringContainsString('Boris', $html);
    }
}
