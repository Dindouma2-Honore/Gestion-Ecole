<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Contracts\AbsenceServiceContract;
use App\Modules\RH\Contracts\PrimeServiceContract;
use App\Modules\RH\Models\AbsencePersonnel;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PersonnelPrime;
use App\Modules\RH\Models\TypePrime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RHPersonnelModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_fixed_and_percentage_primes_are_resolved(): void
    {
        $user = User::factory()->create();
        $employe = Employe::create(['matricule' => 'P-1', 'nom' => 'Doe', 'prenom' => 'Jane', 'poste' => 'Agent', 'date_embauche' => today(), 'statut' => 'actif']);
        Contrat::create(['employe_id' => $employe->id, 'type' => 'CDI', 'categorie_paie' => 'fixe', 'date_debut' => today()->subYear(), 'salaire_base' => 200000, 'statut' => 'actif']);
        $types = [
            TypePrime::create(['code' => 'FIXE', 'libelle' => 'Fixe', 'mode_calcul' => 'montant_fixe', 'valeur_defaut' => 15000]),
            TypePrime::create(['code' => 'PCT', 'libelle' => 'Pourcentage', 'mode_calcul' => 'pourcentage_salaire', 'valeur_defaut' => 10]),
        ];
        foreach ($types as $type) {
            PersonnelPrime::create(['employe_id' => $employe->id, 'type_prime_id' => $type->id, 'date_attribution' => today()->startOfMonth(), 'actif' => true, 'motif' => 'Attribution', 'created_by' => $user->id]);
        }

        $montants = app(PrimeServiceContract::class)->getPrimesActives($employe->id, today())->pluck('montant')->all();
        $this->assertEqualsCanonicalizing([15000.0, 20000.0], $montants);
    }

    public function test_only_validated_unjustified_absence_is_exposed_to_payroll(): void
    {
        $user = User::factory()->create();
        $employe = Employe::create(['matricule' => 'P-2', 'nom' => 'Doe', 'prenom' => 'John', 'poste' => 'Agent', 'date_embauche' => today(), 'statut' => 'actif']);
        foreach ([['conge', 'validee'], ['absence_non_justifiee', 'en_attente'], ['absence_non_justifiee', 'validee']] as [$type, $statut]) {
            AbsencePersonnel::create(['employe_id' => $employe->id, 'date_debut' => today(), 'date_fin' => today(), 'type' => $type, 'motif' => 'Test', 'statut' => $statut, 'created_by' => $user->id]);
        }

        $resultat = app(AbsenceServiceContract::class)->getAbsencesNonJustifiees($employe->id, today()->startOfMonth(), today()->endOfMonth());
        $this->assertCount(1, $resultat);
    }
}
