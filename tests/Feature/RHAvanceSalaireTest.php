<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\RH\Contracts\PaieServiceContract;
use App\Modules\RH\Contracts\PointageServiceContract;
use App\Modules\RH\Models\AvanceSalaire;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RHAvanceSalaireTest extends TestCase
{
    use RefreshDatabase;

    private PaieServiceContract $paie;

    private Employe $employe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        $this->employe = Employe::create([
            'user_id' => $user->id, 'matricule' => 'EMP-AV-001', 'nom' => 'Test',
            'prenom' => 'Avance', 'date_embauche' => today()->subYear(), 'poste' => 'Agent', 'statut' => 'actif',
        ]);
        Contrat::create([
            'employe_id' => $this->employe->id, 'type' => 'CDI', 'date_debut' => today()->subYear(),
            'salaire_base' => 100000, 'statut' => 'actif',
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
        $this->paie = app(PaieServiceContract::class);
    }

    public function test_advance_is_capped_at_thirty_percent_without_founder_derogation(): void
    {
        $this->expectException(\DomainException::class);
        $this->paie->demanderAvance($this->employe->id, 30001, 'Besoin personnel');
    }

    public function test_validated_advance_is_disbursed_and_remainder_is_carried_to_next_payroll(): void
    {
        $avance = $this->paie->demanderAvance($this->employe->id, 150000, 'Urgence familiale', true, 'Dérogation exceptionnelle approuvée');
        app(CaisseServiceContract::class)->ouvrirSession(200000);
        $this->paie->validerAvance($avance->id, 'Décaissement autorisé');

        $premier = $this->paie->calculerBulletin($this->employe->id, 9, 2026);
        $this->assertSame(95800.0, (float) $premier->avances_deduites);
        $this->assertSame(0.0, (float) $premier->net_a_payer);
        $this->assertDatabaseHas('avances_salaires', [
            'id' => $avance->id, 'statut' => 'approuvee', 'montant_deja_deduit' => 95800,
        ]);

        $second = $this->paie->calculerBulletin($this->employe->id, 10, 2026);
        $this->assertSame(54200.0, (float) $second->avances_deduites);
        $this->assertSame(41600.0, (float) $second->net_a_payer);
        $this->assertSame('remboursee', AvanceSalaire::findOrFail($avance->id)->statut);
        $this->assertDatabaseHas('mouvements_caisse', ['rubrique' => 'Avances de salaire', 'montant' => 150000]);
    }
}
