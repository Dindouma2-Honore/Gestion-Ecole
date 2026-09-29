<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Models\GrilleSalariale;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Contracts\HeuresTravailleesServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Exceptions\BulletinDejaExistantException;
use App\Modules\RH\Exceptions\BulletinDejaValideException;
use App\Modules\RH\Exceptions\ContratChevauchementException;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\CategoriePersonnel;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Prime;
use App\Modules\RH\Models\TypePrime;
use App\Modules\Socle\Models\AnneeScolaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RHPaieModule3Test extends TestCase
{
    use RefreshDatabase;

    private Employe $employe;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $this->employe = Employe::create([
            'user_id' => $user->id, 'matricule' => 'PAIE-TEST-001', 'nom' => 'Test',
            'prenom' => 'Paie', 'date_embauche' => today()->subYear(), 'poste' => 'Agent', 'statut' => 'actif',
        ]);
        AnneeScolaire::create([
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active',
        ]);
        $this->app->instance(PointageServiceContract::class, new class implements PointageServiceContract
        {
            public function enregistrerPointage(int $employeId, \DateTimeInterface $dateHeure, string $type, string $modePointage, ?string $terminalId = null): void {}

            public function corrigerManuel(int $pointageId, ?string $heureArrivee, ?string $heureDepart, string $motif): void {}

            public function getJoursAbsenceNonJustifiee(int $employeId, int $mois, int $annee): int
            {
                return 0;
            }

            public function genererRapportMensuel(int $employeId, int $mois, int $annee): object
            {
                return (object) [];
            }
        });
    }

    public function test_fixed_payroll_adds_validated_primes_and_deducts_configured_contributions(): void
    {
        $contrat = $this->contrat(['categorie_paie' => 'fixe', 'salaire_base' => 100000]);
        $type = TypePrime::create(['code' => 'TEST', 'libelle' => 'Prime test', 'mode_calcul' => 'fixe', 'valeur_defaut' => 0]);
        Prime::create([
            'employe_id' => $this->employe->id, 'type_prime_id' => $type->id, 'montant' => 10000,
            'mois' => 9, 'annee' => 2026, 'statut' => 'validee', 'proposee_par' => User::firstOrFail()->id,
        ]);

        $bulletin = app(PaieServiceContract::class)->calculerBulletin($this->employe->id, 9, 2026);

        $this->assertSame($contrat->id, $bulletin->contrat_id);
        $this->assertSame(10000.0, (float) $bulletin->total_primes);
        $this->assertSame(4200.0, (float) $bulletin->total_cotisations);
        $this->assertSame(105800.0, (float) $bulletin->net_a_payer);
    }

    public function test_hourly_payroll_uses_rate_times_hours_without_primes(): void
    {
        $this->contrat(['categorie_paie' => 'horaire', 'salaire_base' => 0, 'taux_horaire' => 2000]);
        $this->app->instance(HeuresTravailleesServiceContract::class, new class implements HeuresTravailleesServiceContract
        {
            public function getNombreHeuresTravaillees(int $employeId, int $mois, int $annee): float
            {
                return 80;
            }
        });

        $bulletin = app(PaieServiceContract::class)->calculerBulletin($this->employe->id, 9, 2026);

        $this->assertSame(160000.0, (float) $bulletin->salaire_base);
        $this->assertSame(0.0, (float) $bulletin->total_primes);
        $this->assertSame(6720.0, (float) $bulletin->total_cotisations);
        $this->assertSame(153280.0, (float) $bulletin->net_a_payer);
    }

    public function test_mixed_responsibilities_are_combined_on_one_payroll(): void
    {
        $this->contrat(['categorie_paie' => 'mixte', 'salaire_base' => 100000, 'taux_horaire' => 2000]);
        $type = TypePrime::create(['libelle' => 'Prime mixte', 'mode_calcul' => 'montant_fixe', 'valeur_defaut' => 10000]);
        Prime::create([
            'employe_id' => $this->employe->id, 'type_prime_id' => $type->id, 'montant' => 10000,
            'mois' => 9, 'annee' => 2026, 'statut' => 'validee', 'proposee_par' => User::firstOrFail()->id,
        ]);
        $this->app->instance(HeuresTravailleesServiceContract::class, new class implements HeuresTravailleesServiceContract
        {
            public function getNombreHeuresTravaillees(int $employeId, int $mois, int $annee): float
            {
                return 20;
            }
        });

        $bulletin = app(PaieServiceContract::class)->calculerBulletin($this->employe->id, 9, 2026);

        $this->assertSame(140000.0, (float) $bulletin->salaire_base);
        $this->assertSame(10000.0, (float) $bulletin->total_primes);
        $this->assertSame(5880.0, (float) $bulletin->total_cotisations);
        $this->assertSame(144120.0, (float) $bulletin->net_a_payer);
        $this->assertCount(4, $bulletin->lignes);
        $this->assertTrue($bulletin->lignes->contains(fn ($ligne): bool => str_starts_with($ligne->libelle, 'Rémunération horaire')));
    }

    public function test_a_payroll_cannot_be_calculated_twice_for_the_same_month(): void
    {
        $this->contrat();
        $service = app(PaieServiceContract::class);
        $service->calculerBulletin($this->employe->id, 9, 2026);

        $this->expectException(BulletinDejaExistantException::class);
        $service->calculerBulletin($this->employe->id, 9, 2026);
    }

    public function test_validated_or_paid_payroll_is_immutable(): void
    {
        $contrat = $this->contrat();
        $bulletin = BulletinPaie::create([
            'employe_id' => $this->employe->id, 'contrat_id' => $contrat->id, 'mois' => 9, 'annee' => 2026,
            'annee_scolaire_id' => AnneeScolaire::firstOrFail()->id, 'salaire_base' => 100000,
            'net_a_payer' => 100000, 'statut' => 'valide',
        ]);

        try {
            $bulletin->update(['net_a_payer' => 1]);
            $this->fail('Un bulletin validé doit être immuable.');
        } catch (BulletinDejaValideException) {
            $this->assertSame(100000.0, (float) $bulletin->fresh()->net_a_payer);
        }

        BulletinPaie::query()->whereKey($bulletin->id)->update(['statut' => 'paye']);
        $bulletin->refresh();
        $this->expectException(BulletinDejaValideException::class);
        $bulletin->delete();
    }

    public function test_an_active_contract_prevents_a_second_contract(): void
    {
        $this->contrat();
        $this->expectException(ContratChevauchementException::class);
        app(ContratServiceInterface::class)->creerContrat([
            'employe_id' => $this->employe->id, 'type' => 'CDD', 'categorie_paie' => 'fixe',
            'date_debut' => today(), 'salaire_base' => 120000,
        ]);
    }

    public function test_seniority_progression_updates_category_and_salary_grid_automatically(): void
    {
        $junior = CategoriePersonnel::query()->where('nom', 'Junior')->firstOrFail();
        $confirme = CategoriePersonnel::query()->where('nom', 'Confirmé')->firstOrFail();
        $ligneJunior = GrilleSalariale::query()->create([
            'categorie_personnel_id' => $junior->id, 'base_calcul' => 'generale',
            'salaire_base' => 100000, 'taux_horaire' => 0, 'date_effet' => '2026-09-02', 'actif' => true,
        ]);
        $ligneConfirme = GrilleSalariale::query()->create([
            'categorie_personnel_id' => $confirme->id, 'base_calcul' => 'generale',
            'salaire_base' => 150000, 'taux_horaire' => 0, 'date_effet' => '2026-09-02', 'actif' => true,
        ]);
        $this->employe->update(['date_embauche' => '2023-09-01', 'categorie_anciennete_id' => $junior->id]);
        $contrat = $this->contrat(['grille_salariale_id' => $ligneJunior->id, 'salaire_base' => 100000]);

        $bulletin = app(PaieServiceContract::class)->calculerBulletin($this->employe->id, 9, 2026);

        $this->assertSame($confirme->id, $this->employe->fresh()->categorie_anciennete_id);
        $this->assertSame($ligneConfirme->id, $contrat->fresh()->grille_salariale_id);
        $this->assertSame(150000.0, (float) $bulletin->salaire_base);
    }

    private function contrat(array $attributes = []): Contrat
    {
        return Contrat::create(array_merge([
            'employe_id' => $this->employe->id, 'type' => 'CDI', 'categorie_paie' => 'fixe',
            'date_debut' => today()->subYear(), 'salaire_base' => 100000, 'statut' => 'actif',
        ], $attributes));
    }
}
