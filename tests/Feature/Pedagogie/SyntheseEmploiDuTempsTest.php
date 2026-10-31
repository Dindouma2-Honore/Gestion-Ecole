<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Filament\Resources\EmploiDuTempsResource\Widgets\SyntheseClasseWidget;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\CategorieMatiere;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Cycle;
use App\Modules\Socle\Models\Niveau;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SyntheseEmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    public function test_synthese_employment_hours_use_offers_assignments_and_existing_schedule(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $annee = AnneeScolaire::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active',
        ]);
        $cycle = Cycle::create(['nom' => 'Maternelle', 'code' => 'MAT-EMP', 'ordre' => 1]);
        $niveau = Niveau::create(['nom' => 'Petite Section', 'code' => 'PS-EMP', 'cycle_id' => $cycle->id, 'ordre' => 1]);
        $classe = Classe::create(['nom' => 'PS A', 'code' => 'PS-A-EMP', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id, 'capacite_max' => 20]);

        $employeId = DB::table('employes')->insertGetId([
            'matricule' => 'ENS-EMP-TEST', 'nom' => 'Nkomo', 'prenom' => 'Aline', 'poste' => 'Enseignante',
            'date_embauche' => '2024-09-01', 'statut' => 'actif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $enseignantId = DB::table('enseignants')->insertGetId([
            'employe_id' => $employeId, 'statut_contractuel' => 'titulaire', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $matiere = Matiere::create([
            'nom' => 'Éveil', 'type' => Matiere::TYPE_DOMAINE_ACTIVITE,
            'categorie_matiere_id' => CategorieMatiere::query()->where('code', 'NON_CLASSEE')->value('id'),
            'code' => 'EVEIL-EMP', 'coefficient_defaut' => 1, 'actif' => true,
        ]);
        $offre = OffrePedagogique::create([
            'matiere_id' => $matiere->id, 'classe_id' => $classe->id, 'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $annee->id, 'coefficient_matiere' => 1, 'heures_hebdomadaires' => 4, 'actif' => true,
        ]);
        AffectationPedagogique::create([
            'enseignant_id' => $enseignantId, 'matiere_id' => $matiere->id, 'classe_id' => $classe->id,
            'annee_scolaire_id' => $annee->id, 'offre_pedagogique_id' => $offre->id,
            'actif' => true,
        ]);
        $creneau = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '09:30:00']);
        EmploiDuTemps::create([
            'classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'enseignant_id' => $enseignantId,
            'salle_id' => 1, 'creneau_id' => $creneau->id, 'annee_scolaire_id' => $annee->id, 'actif' => true,
        ]);

        Livewire::test(SyntheseClasseWidget::class)
            ->assertSee('Année scolaire')->assertSee('Cycle')->assertSee('Niveau')->assertSee('Classe')
            ->set('anneeScolaireId', $annee->id)
            ->set('cycleFiltre', 'Maternelle')
            ->assertSee('Petite Section')
            ->set('niveauFiltre', 'Petite Section')
            ->assertSee('PS A')
            ->set('classeId', $classe->id)
            ->assertSee('Éveil')->assertSee('Aline Nkomo')
            ->assertSee('4 h')->assertSee('1.5 h')->assertSee('2.5 h');
    }
}
