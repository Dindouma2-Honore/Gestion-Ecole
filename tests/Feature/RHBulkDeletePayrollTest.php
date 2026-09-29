<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Resources\BulletinPaieResource\Pages\ListBulletinPaies;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RHBulkDeletePayrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_can_delete_all_payroll_including_validated_records(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $founder = User::factory()->create(['statut' => 'actif']);
        $founder->assignRole('Fondateur');
        $this->actingAs($founder);
        $employe = Employe::query()->create(['matricule' => 'PAY-DEL-01', 'nom' => 'Test', 'prenom' => 'Delete', 'poste' => 'Comptable', 'date_embauche' => today(), 'statut' => 'actif']);
        $contrat = Contrat::query()->create(['employe_id' => $employe->id, 'type' => 'CDI', 'categorie_paie' => 'fixe', 'date_debut' => today(), 'salaire_base' => 50000, 'statut' => 'actif']);
        $annee = AnneeScolaire::query()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30', 'statut' => 'active']);
        BulletinPaie::query()->create(['employe_id' => $employe->id, 'contrat_id' => $contrat->id, 'annee_scolaire_id' => $annee->id, 'mois' => 9, 'annee' => 2026, 'salaire_base' => 50000, 'net_a_payer' => 50000, 'statut' => 'valide']);

        Livewire::test(ListBulletinPaies::class)->callAction('supprimer_tous');

        $this->assertDatabaseCount('bulletins_paie', 0);
        $this->assertDatabaseCount('bulletin_lignes', 0);
    }
}
