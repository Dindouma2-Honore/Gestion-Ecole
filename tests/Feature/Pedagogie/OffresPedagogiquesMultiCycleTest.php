<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages\CreateOffrePedagogique;
use App\Modules\Pedagogie\Models\CategorieMatiere;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OffresPedagogiquesMultiCycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_offer_creation_accepts_active_classes_from_all_school_cycles(): void
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

        $classes = collect([
            ['cycle' => 'Maternelle', 'cycle_code' => 'MAT-TEST', 'niveau' => 'Nursery', 'classe' => 'Nursery A'],
            ['cycle' => 'Primaire', 'cycle_code' => 'PRI-TEST', 'niveau' => 'Class 3', 'classe' => 'Class 3 A'],
            ['cycle' => 'Collège', 'cycle_code' => 'COL-TEST', 'niveau' => '6e', 'classe' => '6e A'],
            ['cycle' => 'Lycée', 'cycle_code' => 'LYC-TEST', 'niveau' => 'Terminale', 'classe' => 'Terminale A'],
        ])->map(function (array $definition) use ($annee): Classe {
            $cycle = Cycle::create(['nom' => $definition['cycle'], 'code' => $definition['cycle_code'], 'actif' => true]);
            $niveau = Niveau::create([
                'nom' => $definition['niveau'],
                'code' => strtoupper(str_replace(' ', '-', $definition['niveau'])).'-TEST',
                'cycle_id' => $cycle->id,
                'ordre' => 1,
            ]);

            return Classe::create([
                'nom' => $definition['classe'],
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $annee->id,
                'capacite_max' => 30,
                'statut' => 'active',
            ]);
        });

        $this->assertCount(4, OffrePedagogiqueResource::classesDisponibles());

        foreach ($classes as $index => $classe) {
            $this->assertArrayHasKey($classe->id, OffrePedagogiqueResource::classesDisponibles());

            Livewire::test(CreateOffrePedagogique::class)
                ->assertFormFieldExists('classe_id')
                ->fillForm([
                    'niveau_id' => $classe->niveau_id,
                    'classe_id' => $classe->id,
                    'matiere_id' => $matiere->id,
                    'coefficient_matiere' => 1,
                    'heures_hebdomadaires' => 4 + $index,
                    'actif' => true,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $offre = OffrePedagogique::query()
                ->where('classe_id', $classe->id)
                ->where('matiere_id', $matiere->id)
                ->where('annee_scolaire_id', $annee->id)
                ->first();

            $this->assertNotNull($offre, "Aucune offre créée pour {$classe->nom}.");
            $this->assertSame($matiere->id, $offre->matiere_id);
            $this->assertSame($annee->id, $offre->annee_scolaire_id);
        }
    }
}
