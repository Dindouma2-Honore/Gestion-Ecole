<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\DepenseServiceContract;
use App\Modules\Finances\Contracts\GestionDepenseServiceContract;
use App\Modules\Finances\Filament\Pages\SituationFinanciere;
use App\Modules\Finances\Models\Depense;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class FinancesE44Test extends TestCase
{
    use RefreshDatabase;

    private GestionDepenseServiceContract $depenses;

    private User $fondateur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        AnneeScolaire::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31', 'statut' => 'active',
        ]);
        $this->fondateur = User::factory()->create(['statut' => 'actif']);
        $this->fondateur->assignRole('Fondateur');
        $this->depenses = app(GestionDepenseServiceContract::class);
    }

    public function test_only_founder_creates_expense_categories(): void
    {
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($comptable);

        $this->expectException(AccessDeniedHttpException::class);
        $this->depenses->creerRubrique('Fournitures');
    }

    public function test_large_expense_requires_founder_validation_then_opens_cash_session_automatically(): void
    {
        $this->actingAs($this->fondateur);
        $rubrique = $this->depenses->creerRubrique('Fournitures');
        $depense = $this->depenses->creerDepense($rubrique->id, 'Manuels', 150000, 'Commande annuelle');

        $this->assertSame('en_attente_validation', $depense->statut);
        $this->depenses->valider($depense->id, 'Budget approuvé');

        $payee = $this->depenses->marquerPayee($depense->id);

        $this->assertSame('payee', $payee->statut);
        $this->assertDatabaseHas('sessions_caisse', ['statut' => 'ouverte']);
        $this->assertDatabaseHas('mouvements_caisse', [
            'type' => 'decaissement', 'rubrique' => 'Fournitures', 'montant' => 150000,
            'source_type' => Depense::class, 'source_id' => $depense->id,
        ]);
        $anneeId = AnneeScolaire::firstOrFail()->id;
        $this->assertSame(150000.0, app(DepenseServiceContract::class)->getTotalDepenseParCategorie($rubrique->id, $anneeId));
    }

    public function test_expense_below_configured_threshold_remains_auto_validated(): void
    {
        $this->actingAs($this->fondateur);
        $rubrique = $this->depenses->creerRubrique('Petites fournitures');
        $depense = $this->depenses->creerDepense($rubrique->id, 'Craies', 99999, 'Besoin courant');

        $this->assertSame('validee', $depense->statut);
        $this->assertNull($depense->workflow_instance_id);
        $this->assertSame($this->fondateur->id, $depense->valide_par);
    }

    public function test_expense_screens_follow_finance_roles(): void
    {
        $this->actingAs($this->fondateur);

        $this->get('/admin/depenses')->assertSuccessful();
        $this->get('/admin/rubrique-depenses')->assertSuccessful();
        $this->get(SituationFinanciere::getUrl())->assertSuccessful()->assertSee('Situation financière des élèves');
    }
}
