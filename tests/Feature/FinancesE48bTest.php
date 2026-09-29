<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\BalanceDepenseProviderContract;
use App\Modules\Finances\Contracts\BalanceServiceContract;
use App\Modules\Finances\Filament\Pages\BalancePeriode;
use App\Modules\Finances\Models\MouvementCaisse;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\SessionCaisse;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FinancesE48bTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_balance_defaults_to_today_and_only_counts_cash_movements(): void
    {
        CarbonImmutable::setTestNow('2026-08-15 12:00:00');
        $this->creerPaiement(120000, 'valide', '2026-08-15 09:00:00');
        $this->creerPaiement(30000, 'annule', '2026-08-15 10:00:00');
        $this->creerPaiement(50000, 'valide', '2026-08-14 10:00:00');
        $this->app->instance(BalanceDepenseProviderContract::class, $this->depensesFake());
        $session = SessionCaisse::create([
            'date_session' => '2026-08-15', 'solde_ouverture' => 0, 'statut' => 'ouverte',
            'ouverte_par' => User::factory()->create()->id,
        ]);
        MouvementCaisse::create([
            'session_caisse_id' => $session->id, 'type' => 'encaissement', 'rubrique' => 'Autre',
            'montant' => 120000, 'module_origine' => 'Autre', 'sous_module' => 'Autre',
            'created_at' => '2026-08-15 09:00:00', 'updated_at' => '2026-08-15 09:00:00',
        ]);

        $balance = app(BalanceServiceContract::class)->getBalanceDuJour();

        $this->assertSame(120000.0, $balance->total_entrees);
        $this->assertSame(0.0, $balance->recettes_par_groupe['scolarite']);
        $this->assertSame(120000.0, $balance->recettes_par_groupe['autres']);
        $this->assertSame($balance->total_entrees, array_sum($balance->recettes_par_groupe));
        $this->assertSame(0.0, $balance->total_sorties);
        $this->assertSame(120000.0, $balance->solde_net);
        $this->assertSame(1, $balance->nombre_operations);
        $this->assertSame('2026-08-15 00:00:00', $balance->periode['debut']->toDateTimeString());
        $this->assertSame('2026-08-15 23:59:59', $balance->periode['fin']->toDateTimeString());
    }

    public function test_pending_expenses_are_separate_from_actual_outflows(): void
    {
        $this->app->instance(BalanceDepenseProviderContract::class, $this->depensesFake());

        $attente = app(BalanceServiceContract::class)->getSortiesEnAttenteValidation(
            CarbonImmutable::parse('2026-08-15'),
            CarbonImmutable::parse('2026-08-15'),
        );

        $this->assertCount(1, $attente);
        $this->assertSame('Réparation toiture', $attente->first()->libelle);
    }

    public function test_e44_provider_is_available_by_default(): void
    {
        $balance = app(BalanceServiceContract::class)->getBalanceDuJour();

        $this->assertSame(0.0, $balance->total_sorties);
    }

    public function test_authenticated_user_can_open_balance_screen(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $this->get(BalancePeriode::getUrl())
            ->assertSuccessful()
            ->assertSee('Journal des opérations');
    }

    private function creerPaiement(float $montant, string $statut, string $date): void
    {
        $utilisateur = User::factory()->create();
        Paiement::unguarded(fn () => Paiement::create([
            'eleve_id' => 1,
            'annee_scolaire_id' => $this->creerAnnee(),
            'montant' => $montant,
            'mode' => 'bancaire',
            'numero_recu' => uniqid('REC-', true),
            'statut' => $statut,
            'encaisse_par' => $utilisateur->id,
            'created_at' => $date,
            'updated_at' => $date,
        ]));
    }

    private function creerAnnee(): int
    {
        return (int) \DB::table('annees_scolaires')->insertGetId([
            'libelle' => uniqid('Année-'),
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'statut' => 'brouillon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function depensesFake(): BalanceDepenseProviderContract
    {
        return new class implements BalanceDepenseProviderContract
        {
            public function getDepensesPayees(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): Collection
            {
                return collect([(object) ['id' => 1, 'montant' => 45000, 'created_at' => CarbonImmutable::parse('2026-08-15 11:00'), 'reference' => 'DEP-1']]);
            }

            public function getDepensesEnAttenteValidation(\DateTimeInterface $dateDebut, \DateTimeInterface $dateFin): Collection
            {
                return collect([(object) ['id' => 2, 'montant' => 800000, 'created_at' => CarbonImmutable::parse('2026-08-15 11:30'), 'libelle' => 'Réparation toiture']]);
            }
        };
    }
}
