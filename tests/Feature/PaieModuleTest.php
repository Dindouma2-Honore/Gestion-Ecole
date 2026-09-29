<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Contracts\PrimeServiceContract;
use App\Modules\RH\Exceptions\BulletinDejaExistantException;
use App\Modules\RH\Exceptions\BulletinDejaValideException;
use App\Modules\RH\Exceptions\ContratInexistantException;
use App\Modules\RH\Models\AvanceSalaire;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\EtatVirement;
use App\Modules\Socle\Models\AnneeScolaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaieModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create base active school year
        AnneeScolaire::create([
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-06-30',
            'statut' => 'active',
        ]);
    }

    public function test_calculer_bulletin_sans_contrat_leve_exception(): void
    {
        $employe = Employe::create([
            'matricule' => 'EMP-001',
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'date_embauche' => '2025-01-01',
            'poste' => 'Comptable',
            'statut' => 'actif',
        ]);

        $service = app(PaieServiceContract::class);

        $this->expectException(ContratInexistantException::class);
        $service->calculerBulletin($employe->id, 9, 2026);
    }

    public function test_calculer_bulletin_avec_contrat_primes_absences_et_avances(): void
    {
        $employe = Employe::create([
            'matricule' => 'EMP-002',
            'nom' => 'Traoré',
            'prenom' => 'Awa',
            'date_embauche' => '2025-01-01',
            'poste' => 'Enseignante',
            'statut' => 'actif',
        ]);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'date_debut' => '2025-01-01',
            'salaire_base' => 220000.00,
            'statut' => 'actif',
        ]);

        // Mock PrimeServiceContract returning a 30 000 FCFA prime
        $this->mock(PrimeServiceContract::class, function ($mock) {
            $mock->shouldReceive('getPrimesActives')
                ->andReturn(collect([
                    (object) ['libelle' => 'Prime d’ancienneté', 'montant' => 30000.00],
                ]));
        });

        // Mock AbsenceServiceContract returning 2 days unjustified absence and 0 suspension days
        $this->mock(AbsenceServiceContract::class, function ($mock) use ($employe) {
            $mock->shouldReceive('getJoursAbsenceNonJustifiee')
                ->with($employe->id, 9, 2026)
                ->andReturn(2);
            $mock->shouldReceive('getJoursSuspension')
                ->with($employe->id, 9, 2026)
                ->andReturn(0);
        });

        // Create an approved advance of 20 000 FCFA
        $avance = AvanceSalaire::create([
            'employe_id' => $employe->id,
            'montant' => 20000.00,
            'date_demande' => '2026-09-01',
            'statut' => 'approuvee',
            'montant_deja_deduit' => 0.00,
        ]);

        $service = app(PaieServiceContract::class);
        /** @var BulletinPaie $bulletin */
        $bulletin = $service->calculerBulletin($employe->id, 9, 2026);

        $this->assertEquals($employe->id, $bulletin->employe_id);
        $this->assertEquals(220000.00, $bulletin->salaire_base);
        $this->assertEquals(30000.00, $bulletin->total_primes);

        // Retenue pour 2 jours d'absence (220 000 / 22 * 2 = 20 000)
        $this->assertEquals(20000.00, $bulletin->total_retenues);

        // Cotisations CNPS (4.2% de 220 000 = 9 240)
        $this->assertEquals(9240.00, $bulletin->total_cotisations);

        // Avance déduite = 20 000
        $this->assertEquals(20000.00, $bulletin->avances_deduites);

        // Net à payer = 220 000 + 30 000 - 20 000 - 9 240 - 20 000 = 200 760
        $this->assertEquals(200760.00, $bulletin->net_a_payer);
        $this->assertEquals('calcule', $bulletin->statut);

        // Verify detail lines were created
        $this->assertCount(5, $bulletin->lignes);

        // Verify advance was marked as fully deducted / status updated to remboursee
        $avance->refresh();
        $this->assertEquals(20000.00, $avance->montant_deja_deduit);
        $this->assertEquals(0.00, $avance->resteADeduire());
        $this->assertEquals('remboursee', $avance->statut);
    }

    public function test_tentative_de_calculer_bulletin_en_double_leve_exception(): void
    {
        $employe = Employe::create([
            'matricule' => 'EMP-003',
            'nom' => 'Yao',
            'prenom' => 'Koffi',
            'date_embauche' => '2025-01-01',
            'poste' => 'Technicien',
            'statut' => 'actif',
        ]);

        Contrat::create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'date_debut' => '2025-01-01',
            'salaire_base' => 180000.00,
            'statut' => 'actif',
        ]);

        $this->mock(PrimeServiceContract::class, fn ($mock) => $mock->shouldReceive('getPrimesActives')->andReturn(collect()));
        $this->mock(AbsenceServiceContract::class, function ($mock) {
            $mock->shouldReceive('getJoursAbsenceNonJustifiee')->andReturn(0);
            $mock->shouldReceive('getJoursSuspension')->andReturn(0);
        });

        $service = app(PaieServiceContract::class);
        $service->calculerBulletin($employe->id, 9, 2026);

        $this->expectException(BulletinDejaExistantException::class);
        $service->calculerBulletin($employe->id, 9, 2026);
    }

    public function test_bulletin_valide_est_immuable_par_observer(): void
    {
        $employe = Employe::create([
            'matricule' => 'EMP-004',
            'nom' => 'Koné',
            'prenom' => 'Bakary',
            'date_embauche' => '2025-01-01',
            'poste' => 'Chauffeur',
            'statut' => 'actif',
        ]);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'date_debut' => '2025-01-01',
            'salaire_base' => 150000.00,
            'statut' => 'actif',
        ]);

        $this->mock(PrimeServiceContract::class, fn ($mock) => $mock->shouldReceive('getPrimesActives')->andReturn(collect()));
        $this->mock(AbsenceServiceContract::class, function ($mock) {
            $mock->shouldReceive('getJoursAbsenceNonJustifiee')->andReturn(0);
            $mock->shouldReceive('getJoursSuspension')->andReturn(0);
        });

        $service = app(PaieServiceContract::class);
        /** @var BulletinPaie $bulletin */
        $bulletin = $service->calculerBulletin($employe->id, 9, 2026);

        $service->validerBulletin($bulletin->id, 1);
        $bulletin->refresh();

        $this->assertEquals('valide', $bulletin->statut);

        // Attempting to modify salary on validated bulletin via Eloquent update must throw BulletinDejaValideException
        $this->expectException(BulletinDejaValideException::class);
        $bulletin->update(['salaire_base' => 999999.00]);
    }

    public function test_suppression_de_bulletin_valide_interdite(): void
    {
        $employe = Employe::create([
            'matricule' => 'EMP-005',
            'nom' => 'Bamba',
            'prenom' => 'Sékou',
            'date_embauche' => '2025-01-01',
            'poste' => 'Gardien',
            'statut' => 'actif',
        ]);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'date_debut' => '2025-01-01',
            'salaire_base' => 120000.00,
            'statut' => 'actif',
        ]);

        $this->mock(PrimeServiceContract::class, fn ($mock) => $mock->shouldReceive('getPrimesActives')->andReturn(collect()));
        $this->mock(AbsenceServiceContract::class, function ($mock) {
            $mock->shouldReceive('getJoursAbsenceNonJustifiee')->andReturn(0);
            $mock->shouldReceive('getJoursSuspension')->andReturn(0);
        });

        $service = app(PaieServiceContract::class);
        /** @var BulletinPaie $bulletin */
        $bulletin = $service->calculerBulletin($employe->id, 9, 2026);

        $service->validerBulletin($bulletin->id, 1);
        $bulletin->refresh();

        $this->expectException(BulletinDejaValideException::class);
        $bulletin->delete();
    }

    public function test_generation_etat_virement(): void
    {
        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);

        $employe = Employe::create([
            'matricule' => 'EMP-006',
            'nom' => 'Diallo',
            'prenom' => 'Amadou',
            'date_embauche' => '2025-01-01',
            'poste' => 'Professeur',
            'statut' => 'actif',
        ]);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'date_debut' => '2025-01-01',
            'salaire_base' => 300000.00,
            'statut' => 'actif',
        ]);

        $this->mock(PrimeServiceContract::class, fn ($mock) => $mock->shouldReceive('getPrimesActives')->andReturn(collect()));
        $this->mock(AbsenceServiceContract::class, function ($mock) {
            $mock->shouldReceive('getJoursAbsenceNonJustifiee')->andReturn(0);
            $mock->shouldReceive('getJoursSuspension')->andReturn(0);
        });

        $service = app(PaieServiceContract::class);
        /** @var BulletinPaie $bulletin */
        $bulletin = $service->calculerBulletin($employe->id, 9, 2026);
        $service->validerBulletin($bulletin->id, 1);

        /** @var EtatVirement $etat */
        $etat = $service->genererEtatVirement(9, 2026);

        $this->assertInstanceOf(EtatVirement::class, $etat);
        $this->assertEquals(9, $etat->mois);
        $this->assertEquals(2026, $etat->annee);
        $this->assertCount(1, $etat->bulletins);
    }
}
