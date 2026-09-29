<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\BilanJournalierScolariteContract;
use App\Modules\Scolarite\Filament\Pages\BilanJournalier;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScolariteBilanJournalierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_bilan_only_groups_valid_school_payments_for_selected_day(): void
    {
        $anneeId = (int) \DB::table('annees_scolaires')->insertGetId([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $encaisseur = User::factory()->create();

        $this->paiement($anneeId, $encaisseur->id, 15000, 'inscription', 'especes', 'valide', '2026-09-03 08:00:00');
        $this->paiement($anneeId, $encaisseur->id, 125000, 'tranche_1', 'bancaire', 'valide', '2026-09-03 10:00:00');
        $this->paiement($anneeId, $encaisseur->id, 30000, 'tranche_2', 'especes', 'annule', '2026-09-03 11:00:00');
        $this->paiement($anneeId, $encaisseur->id, 50000, 'tranche_2', 'especes', 'valide', '2026-09-02 11:00:00');

        $bilan = app(BilanJournalierScolariteContract::class)->getBilan(CarbonImmutable::parse('2026-09-03'));

        $this->assertSame(140000.0, $bilan->total);
        $this->assertSame(2, $bilan->nombre_paiements);
        $this->assertSame(15000.0, $bilan->totaux_par_type['Inscription']);
        $this->assertSame(125000.0, $bilan->totaux_par_type['Première tranche']);
        $this->assertSame(['bancaire' => 125000.0, 'especes' => 15000.0], $bilan->totaux_par_mode);
    }

    public function test_page_belongs_to_scolarite_and_contains_no_finance_balance_link(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)
            ->get(BilanJournalier::getUrl())
            ->assertSuccessful()
            ->assertSee('Bilan journalier de la scolarité')
            ->assertSee('Totaux par type de frais')
            ->assertDontSee('/admin/finances/balance');

        $this->assertStringContainsString('/admin/scolarite/bilan-journalier', BilanJournalier::getUrl());
    }

    private function paiement(int $anneeId, int $encaisseurId, float $montant, string $rubrique, string $mode, string $statut, string $date): void
    {
        Paiement::unguarded(fn () => Paiement::create([
            'eleve_id' => 1,
            'annee_scolaire_id' => $anneeId,
            'montant' => $montant,
            'mode' => $mode,
            'numero_recu' => uniqid('REC-', true),
            'rubrique' => $rubrique,
            'statut' => $statut,
            'encaisse_par' => $encaisseurId,
            'created_at' => $date,
            'updated_at' => $date,
        ]));
    }
}
