<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\BudgetServiceContract;
use App\Modules\Finances\Contracts\DepenseServiceContract;
use App\Modules\Finances\Exceptions\BudgetDejaExistantException;
use App\Modules\Finances\Filament\Resources\BudgetResource;
use App\Modules\Finances\Filament\Resources\BudgetResource\Pages\CreateBudget;
use App\Modules\Finances\Models\Budget;
use App\Modules\Finances\Models\BudgetCategorie;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinancesE43Test extends TestCase
{
    use RefreshDatabase;

    private BudgetServiceContract $budgets;

    private AnneeScolaire $annee;

    private BudgetCategorie $categorie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
        $this->categorie = BudgetCategorie::create(['nom' => 'Fournitures', 'type' => 'depense']);
        $this->budgets = app(BudgetServiceContract::class);
    }

    public function test_creer_budget_persists_lines_and_refuses_duplicate_year(): void
    {
        $budget = $this->budgets->creerBudgetPrevisionnel($this->annee->id, [[
            'categorie_id' => $this->categorie->id,
            'montant_prevu' => 500000,
        ]]);

        $this->assertSame('brouillon', $budget->statut);
        $this->assertSame(500000.0, (float) $budget->lignes->first()->montant_prevu);

        $this->expectException(BudgetDejaExistantException::class);
        $this->budgets->creerBudgetPrevisionnel($this->annee->id, [[
            'categorie_id' => $this->categorie->id,
            'montant_prevu' => 1,
        ]]);
    }

    public function test_validation_records_validator_and_audit(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $budget = $this->budgets->creerBudgetPrevisionnel($this->annee->id, [[
            'categorie_id' => $this->categorie->id,
            'montant_prevu' => 500000,
        ]]);

        $this->budgets->validerBudget($budget->id, $user->id);

        $this->assertDatabaseHas('budgets', ['id' => $budget->id, 'statut' => 'valide', 'valide_par' => $user->id]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Budget::class,
            'subject_id' => $budget->id,
            'causer_id' => $user->id,
        ]);
    }

    public function test_consumption_uses_e44_read_contract_and_calculates_overrun(): void
    {
        $this->budgets->creerBudgetPrevisionnel($this->annee->id, [[
            'categorie_id' => $this->categorie->id,
            'montant_prevu' => 100000,
        ]]);
        $this->app->instance(DepenseServiceContract::class, new class implements DepenseServiceContract
        {
            public function getTotalDepenseParCategorie(int $categorieId, int $anneeScolaireId): float
            {
                return 125000.0;
            }
        });

        $consommation = $this->budgets->getConsommationCategorie($this->categorie->id, $this->annee->id);

        $this->assertSame(100000.0, $consommation->montant_prevu);
        $this->assertSame(125000.0, $consommation->montant_consomme);
        $this->assertSame(125.0, $consommation->pourcentage_consomme);
        $this->assertTrue($consommation->depassement);
    }

    public function test_consumption_uses_the_bound_e44_service_when_no_expense_exists(): void
    {
        $this->budgets->creerBudgetPrevisionnel($this->annee->id, [[
            'categorie_id' => $this->categorie->id,
            'montant_prevu' => 100000,
        ]]);

        $consommation = $this->budgets->getConsommationCategorie($this->categorie->id, $this->annee->id);

        $this->assertSame(0.0, $consommation->montant_consomme);
        $this->assertSame(0.0, $consommation->pourcentage_consomme);
        $this->assertFalse($consommation->depassement);
    }

    public function test_filament_creates_and_lists_budget_with_repeater(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        Livewire::test(CreateBudget::class)
            ->fillForm([
                'annee_scolaire_id' => $this->annee->id,
                'lignes' => [[
                    'categorie_id' => $this->categorie->id,
                    'montant_prevu' => 250000,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get(BudgetResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('2026-2027');

        $this->assertDatabaseHas('budget_lignes', [
            'categorie_id' => $this->categorie->id,
            'montant_prevu' => 250000,
        ]);
    }
}
