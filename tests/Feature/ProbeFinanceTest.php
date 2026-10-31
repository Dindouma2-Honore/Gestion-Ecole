<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Filament\Resources\ClasseResource\Pages\ListClasses;
use App\Modules\Scolarite\Filament\Resources\FraisResource\Pages\ListFrais;
use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource\Pages\ListGrilleTarifaire;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Filament\Resources\PaiementResource\Pages\ListPaiements;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProbeFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_creer_versement(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['statut' => 'actif']);
        $admin->assignRole('Fondateur');
        $this->actingAs($admin);

        $annee = AnneeScolaire::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'statut' => 'active']);
        $cycle = Cycle::create(['nom' => 'Secondaire', 'code' => 'SEC', 'ordre' => 2]);
        $niveau = Niveau::create(['nom' => '6ème', 'code' => '6E', 'ordre' => 1, 'cycle_id' => $cycle->id]);

        $employe = Employe::create(['matricule' => 'E1', 'nom' => 'P', 'prenom' => 'A', 'poste' => 'Enseignant', 'date_embauche' => today(), 'statut' => 'actif']);
        $enseignant = Enseignant::create(['employe_id' => $employe->id, 'specialite' => 'M', 'statut_contractuel' => 'titulaire', 'charge_horaire_hebdo' => 18]);

        $classe = Classe::create([
            'nom' => '6ème A', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id,
            'capacite_max' => 30, 'statut' => 'active', 'professeur_principal_id' => $enseignant->id,
        ]);

        // Frais + grille via Filament
        Livewire::test(ListFrais::class)->callAction('create', [
            'nom' => 'Scolarité annuelle',
            'categorie_frais_id' => \App\Modules\Scolarite\Models\CategorieFrais::create(['nom' => 'Scol', 'code' => 'SCOL'])->id,
            'utilise_grille_tarifaire' => true,
            'ordre_repartition' => 1,
        ]);
        $frais = \App\Modules\Scolarite\Models\Frais::where('nom', 'Scolarité annuelle')->firstOrFail();

        Livewire::test(ListGrilleTarifaire::class)->callAction('create', [
            'frais_id' => $frais->id,
            'classe_id' => $classe->id,
            'annee_scolaire_id' => $annee->id,
            'montant' => 100000,
        ]);

        // Eleve + parent + inscription via modale
        $eleve = Eleve::create(['nom' => 'X', 'prenom' => 'Y', 'sexe' => 'F', 'statut' => 'admis', 'nom_tuteur' => 'X Parent', 'telephone_tuteur' => '670000001']);
        $eleve->synchroniserParentTuteur();

        Livewire::test(ListInscriptions::class)->callAction('create', [
            'eleve_id' => $eleve->id,
            'classe_id' => $classe->id,
            'montant_verse' => 15000,
            'mode_paiement' => 'especes',
            'valider_paiement' => false,
        ]);

        $inscription = \App\Modules\Scolarite\Models\Inscription::withoutGlobalScopes()->where('eleve_id', $eleve->id)->firstOrFail();
        fwrite(STDERR, "\nINSPECTION frais_eleve count: " . \App\Modules\Scolarite\Models\FraisEleve::where('inscription_id', $inscription->id)->count() . "\n");

        $component = Livewire::test(ListPaiements::class)->callAction('creerVersement', [
            'inscription_id' => $inscription->id,
            'montant' => 50000,
            'mode' => 'especes',
        ]);
        try {
            $component->assertNotified();
            fwrite(STDERR, "NOTIFIED: yes\n");
        } catch (\Throwable $e) {
            fwrite(STDERR, "NOTIFIED: no\n");
        }
        fwrite(STDERR, "PAIEMENTS_SCOLARITE count: " . \App\Modules\Scolarite\Models\Paiement::count() . "\n");
        fwrite(STDERR, "FINANCES PAIEMENTS count: " . \App\Modules\Finances\Models\Paiement::count() . "\n");
        fwrite(STDERR, "RESTE: " . app(\App\Modules\Scolarite\Contracts\PaiementServiceContract::class)->getResteAPayer($inscription->id) . "\n");

        $this->assertTrue(true);
    }
}
