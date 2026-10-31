<?php

declare(strict_types=1);

namespace Tests\Feature\Scolarite;

use App\Models\User;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ParentTuteurResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateur;

    private AnneeScolaire $annee;

    private Classe $classe1;

    private Classe $classe2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            DocumentNumberingSeeder::class,
        ]);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);

        $cycle = Cycle::create(['code' => 'PRI', 'nom' => 'Primaire', 'ordre' => 1]);
        $niveau1 = Niveau::create(['code' => 'CP', 'nom' => 'CP', 'cycle_id' => $cycle->id, 'ordre' => 1]);
        $niveau2 = Niveau::create(['code' => 'CE1', 'nom' => 'CE1', 'cycle_id' => $cycle->id, 'ordre' => 2]);

        $this->classe1 = Classe::create([
            'code' => 'CP-A',
            'nom' => 'CP A',
            'niveau_id' => $niveau1->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'ouverte',
        ]);

        $this->classe2 = Classe::create([
            'code' => 'CE1-B',
            'nom' => 'CE1 B',
            'niveau_id' => $niveau2->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'ouverte',
        ]);

        $this->fondateur = User::factory()->create([
            'name' => 'Fondateur Test',
            'email' => 'fondateur@test.local',
        ]);
        $this->fondateur->assignRole('Fondateur');
    }

    public function test_parent_tuteur_table_renders_three_actions_view_edit_delete(): void
    {
        $this->actingAs($this->fondateur);

        $parent = ParentTuteur::create([
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'profession' => 'Ingénieur',
            'telephone' => '+237699001122',
            'email' => 'jean.dupont@test.com',
            'portail_actif' => true,
        ]);

        Livewire::test(ListParentTuteurs::class)
            ->assertTableActionExists('view')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('delete');
    }

    public function test_parent_tuteur_view_displays_parent_info_and_all_attached_children(): void
    {
        $this->actingAs($this->fondateur);

        $parent = ParentTuteur::create([
            'nom' => 'Kamga',
            'prenom' => 'Paul',
            'profession' => 'Médecin',
            'telephone' => '+237677112233',
            'email' => 'paul.kamga@test.com',
            'portail_actif' => true,
        ]);

        // Premier enfant
        $enfant1 = Eleve::create([
            'matricule_permanent' => 'MAT-KAMGA-01',
            'nom' => 'Kamga',
            'prenom' => 'Amina',
            'sexe' => 'F',
            'date_naissance' => '2016-04-12',
            'statut' => 'inscrit',
        ]);

        $parent->eleves()->attach($enfant1->id, [
            'lien' => 'pere',
            'responsable_legal' => true,
            'responsable_paiement' => true,
            'autorise_recuperation' => true,
        ]);

        Inscription::create([
            'eleve_id' => $enfant1->id,
            'classe_id' => $this->classe1->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'validee',
            'date_inscription' => now()->toDateString(),
        ]);

        // Deuxième enfant
        $enfant2 = Eleve::create([
            'matricule_permanent' => 'MAT-KAMGA-02',
            'nom' => 'Kamga',
            'prenom' => 'David',
            'sexe' => 'M',
            'date_naissance' => '2018-09-20',
            'statut' => 'inscrit',
        ]);

        $parent->eleves()->attach($enfant2->id, [
            'lien' => 'pere',
            'responsable_legal' => true,
            'responsable_paiement' => false,
            'autorise_recuperation' => true,
        ]);

        Inscription::create([
            'eleve_id' => $enfant2->id,
            'classe_id' => $this->classe2->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'en_attente_versement',
            'date_inscription' => now()->toDateString(),
        ]);

        Livewire::test(ViewParentTuteur::class, ['record' => $parent->id])
            ->assertSuccessful()
            ->assertSee('Paul Kamga')
            ->assertSee('Médecin')
            ->assertSee('+237677112233')
            ->assertSee('paul.kamga@test.com')
            ->assertSee('2 enfants')
            ->assertSee('Amina')
            ->assertSee('MAT-KAMGA-01')
            ->assertSee('CP A')
            ->assertSee('David')
            ->assertSee('MAT-KAMGA-02')
            ->assertSee('CE1 B')
            ->assertSee('Financier')
            ->assertSee('Légal')
            ->assertSee('Récupération');
    }

    public function test_parent_with_children_cannot_be_deleted(): void
    {
        $this->actingAs($this->fondateur);

        $parent = ParentTuteur::create([
            'nom' => 'Mbarga',
            'prenom' => 'Samuel',
            'telephone' => '+237655443322',
            'portail_actif' => true,
        ]);

        $enfant = Eleve::create([
            'matricule_permanent' => 'MAT-MBARGA-01',
            'nom' => 'Mbarga',
            'prenom' => 'Junior',
            'sexe' => 'M',
            'statut' => 'admis',
        ]);

        $parent->eleves()->attach($enfant->id, [
            'lien' => 'pere',
            'responsable_legal' => true,
            'responsable_paiement' => true,
            'autorise_recuperation' => true,
        ]);

        // Delete action attempt must be blocked and parent preserved in DB
        Livewire::test(ListParentTuteurs::class)
            ->callTableAction('delete', $parent);

        $this->assertDatabaseHas('parents_tuteurs', ['id' => $parent->id]);

        // Now detach child and verify deletion is permitted
        $parent->eleves()->detach($enfant->id);
        $parent->refresh();

        Livewire::test(ListParentTuteurs::class)
            ->callTableAction('delete', $parent);

        $this->assertDatabaseMissing('parents_tuteurs', ['id' => $parent->id]);
    }
}
