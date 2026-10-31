<?php

declare(strict_types=1);

namespace Tests\Feature\Scolarite;

use App\Models\User;
use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource\Pages\CreateTransfertAffectation;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransfertAffectationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_eleve_peut_etre_transfere_et_l_affectation_origine_est_conservee(): void
    {
        Role::firstOrCreate(['name' => 'Fondateur']);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $niveau = Niveau::create(['nom' => 'Sixième', 'code' => '6E-TR', 'ordre' => 1]);
        $annee = AnneeScolaire::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31', 'statut' => 'active',
        ]);
        $origine = Classe::create([
            'nom' => '6e A', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id,
            'capacite_max' => 30, 'statut' => 'active',
        ]);
        $destination = Classe::create([
            'nom' => '6e B', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $annee->id,
            'capacite_max' => 30, 'statut' => 'active',
        ]);
        $eleve = Eleve::create(['nom' => 'Mbarga', 'prenom' => 'Paul', 'statut' => 'actif']);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $origine->id,
            'annee_scolaire_id' => $annee->id, 'type' => 'inscription',
            'date_inscription' => '2026-09-01', 'statut' => 'active',
        ]);

        Livewire::test(CreateTransfertAffectation::class)
            ->fillForm([
                'inscription_id' => $inscription->id,
                'classe_destination_id' => $destination->id,
                'date_transfert' => '2026-09-10',
                'motif' => 'Rééquilibrage des effectifs.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('inscriptions', [
            'id' => $inscription->id, 'classe_id' => $destination->id, 'type' => 'transfert',
        ]);
        $this->assertDatabaseHas('transferts_affectations', [
            'inscription_id' => $inscription->id,
            'classe_origine_id' => $origine->id,
            'classe_destination_id' => $destination->id,
            'effectue_par' => $user->id,
        ]);
    }
}
