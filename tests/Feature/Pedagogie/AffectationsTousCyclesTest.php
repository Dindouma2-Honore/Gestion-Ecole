<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages\CreateAffectationPedagogique;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\CategorieMatiere;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\OrganisationCycle;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AffectationsTousCyclesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rh_teacher_can_be_assigned_by_subject_to_classes_in_every_cycle(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
        $categorie = CategorieMatiere::query()->where('code', 'NON_CLASSEE')->firstOrFail();
        $matiere = Matiere::create([
            'nom' => 'Mathématiques',
            'categorie_matiere_id' => $categorie->id,
            'actif' => true,
        ]);
        $employe = Employe::create([
            'matricule' => 'ENS-TOUS-CYCLES',
            'nom' => 'Nkomo',
            'prenom' => 'Aline',
            'poste' => 'Enseignante',
            'date_embauche' => '2024-09-01',
            'statut' => 'actif',
        ]);
        $enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'statut_contractuel' => 'titulaire',
        ]);

        $definitions = [
            ['cycle' => 'Maternelle', 'code' => 'MAT-TEST', 'niveau' => 'Nursery', 'classe' => 'Nursery A'],
            ['cycle' => 'Primaire', 'code' => 'PRI-TEST', 'niveau' => 'Class 3', 'classe' => 'Class 3 A'],
            ['cycle' => 'Collège', 'code' => 'COL-TEST', 'niveau' => '6e', 'classe' => '6e A'],
            ['cycle' => 'Lycée', 'code' => 'LYC-TEST', 'niveau' => 'Terminale', 'classe' => 'Terminale A'],
        ];

        foreach ($definitions as $index => $definition) {
            AffectationPedagogiqueResource::context()->reset();
            $cycle = Cycle::create(['nom' => $definition['cycle'], 'code' => $definition['code'], 'actif' => true]);
            OrganisationCycle::create(['cycle_id' => $cycle->id, 'mode' => 'matiere']);
            $niveau = Niveau::create([
                'nom' => $definition['niveau'],
                'code' => strtoupper(str_replace(' ', '-', $definition['niveau'])).'-TEST',
                'cycle_id' => $cycle->id,
                'ordre' => $index + 1,
            ]);
            $classe = Classe::create([
                'nom' => $definition['classe'],
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $annee->id,
                'capacite_max' => 30,
                'statut' => 'active',
            ]);
            $offre = OffrePedagogique::create([
                'matiere_id' => $matiere->id,
                'classe_id' => $classe->id,
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $annee->id,
                'coefficient_matiere' => 1,
                'heures_hebdomadaires' => 4,
                'actif' => true,
            ]);

            $classesDisponibles = AffectationPedagogiqueResource::classesDisponibles();
            $enseignantsDisponibles = AffectationPedagogiqueResource::enseignantsDisponibles();
            $offresDisponibles = AffectationPedagogiqueResource::offresDisponibles($classe->id);

            $this->assertArrayHasKey($classe->id, $classesDisponibles);
            $this->assertArrayHasKey($enseignant->id, $enseignantsDisponibles);
            $this->assertArrayHasKey($offre->id, $offresDisponibles);

            Livewire::test(CreateAffectationPedagogique::class)
                ->fillForm([
                    'annee_scolaire_id' => $annee->id,
                    'classe_id' => $classe->id,
                    'type' => AffectationPedagogique::TYPE_MATIERE,
                    'matiere_id' => $matiere->id,
                    'enseignant_id' => $enseignant->id,
                    'actif' => true,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('affectations_pedagogiques', [
                'classe_id' => $classe->id,
                'annee_scolaire_id' => $annee->id,
                'enseignant_id' => $enseignant->id,
                'matiere_id' => $matiere->id,
                'offre_pedagogique_id' => $offre->id,
                'type' => AffectationPedagogique::TYPE_MATIERE,
                'actif' => true,
            ]);
        }
    }
}
