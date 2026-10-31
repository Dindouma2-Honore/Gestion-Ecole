<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Models\Paiement;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages\CreateClasse;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ClasseCreationCompleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_generates_code_and_records_staff_and_six_school_fees(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['statut' => 'actif']);
        $admin->assignRole('Fondateur');
        $this->actingAs($admin);

        $principalEmploye = Employe::create(['matricule' => 'ENS-1', 'nom' => 'Principal', 'prenom' => 'Paul', 'poste' => 'Enseignant', 'date_embauche' => today(), 'statut' => 'actif']);
        $enseignant = Enseignant::create(['employe_id' => $principalEmploye->id, 'specialite' => 'Généraliste', 'statut_contractuel' => 'titulaire', 'charge_horaire_hebdo' => 20]);
        $assistant = Employe::create(['matricule' => 'ASS-1', 'nom' => 'Assistant', 'prenom' => 'Anne', 'poste' => 'Assistante', 'date_embauche' => today(), 'statut' => 'actif']);
        $niveau = Niveau::create(['nom' => 'CP1', 'code' => 'CP1', 'ordre' => 1]);
        $annee = AnneeScolaire::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'statut' => 'active']);

        $this->get(ClasseResource::getUrl('create'))->assertSuccessful();

        Livewire::test(CreateClasse::class)->fillForm([
            'nom' => 'CP1 A', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id,
            'capacite_max' => 30, 'statut' => 'active', 'professeur_principal_id' => $enseignant->id,
            'assistant_ids' => [$assistant->id], 'frais_inscription' => 15000,
            'tranche_1' => 25000, 'tranche_2' => 25000, 'tranche_3' => 25000,
            'tranche_4' => 25000, 'tranche_5' => 25000,
        ])->call('create')->assertHasNoFormErrors();

        $classe = Classe::query()->where('nom', 'CP1 A')->firstOrFail();
        $this->assertSame('CP1-A', $classe->code);
        $this->assertSame($enseignant->id, $classe->professeur_principal_id);
        $this->assertDatabaseHas('classe_assistants', ['classe_id' => $classe->id, 'employe_id' => $assistant->id]);
        $configuration = DB::table('configurations_frais_classe')->where('classe_id', $classe->id)->first();
        $this->assertSame(15000.0, (float) $configuration->frais_inscription);
        $this->assertSame(125000.0, (float) $configuration->montant_total);
        $this->assertSame(5, DB::table('tranches_frais_classe')->where('configuration_frais_classe_id', $configuration->id)->count());

        $fille = Eleve::create(['nom' => 'Mballa', 'prenom' => 'Anne', 'sexe' => 'F', 'statut' => 'inscrit']);
        $garcon = Eleve::create(['nom' => 'Essomba', 'prenom' => 'Paul', 'sexe' => 'M', 'statut' => 'inscrit']);
        foreach ([$fille, $garcon] as $eleve) {
            Inscription::create([
                'eleve_id' => $eleve->id, 'classe_id' => $classe->id,
                'annee_scolaire_id' => $annee->id, 'type' => 'inscription',
                'date_inscription' => today(), 'statut' => 'validee',
            ]);
        }
        Paiement::create([
            'eleve_id' => $fille->id, 'annee_scolaire_id' => $annee->id,
            'montant' => 30000, 'mode' => 'especes', 'numero_recu' => 'REC-CLASSE-1',
            'statut' => 'valide', 'encaisse_par' => $admin->id,
        ]);

        $resume = ClasseResource::resume($classe);
        $this->assertSame(2, $resume['total']);
        $this->assertSame(1, $resume['filles']);
        $this->assertSame(1, $resume['garcons']);
        $this->assertSame(280000.0, $resume['attendu']);
        $this->assertSame(30000.0, $resume['encaisse']);
        $this->assertSame(250000.0, $resume['reste']);

        $this->get(ClasseResource::getUrl('view', ['record' => $classe]))
            ->assertSuccessful()
            ->assertSee('Configuration de la salle')
            ->assertSee('Bilan financier')
            ->assertSee('Montant total attendu')
            ->assertSee('Montant déjà encaissé');
    }
}
