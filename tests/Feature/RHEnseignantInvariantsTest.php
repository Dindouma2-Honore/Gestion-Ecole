<?php

namespace Tests\Feature;

use App\Modules\RH\Contracts\EnseignantsPedagogiquesReadContract;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RHEnseignantInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employe_peut_exister_sans_enseignant(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-TEST-01',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'poste' => 'Comptable',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->assertNull($employe->enseignant);
        $this->assertDatabaseHas('employes', ['id' => $employe->id]);
        $this->assertDatabaseMissing('enseignants', ['employe_id' => $employe->id]);
    }

    public function test_enseignant_reference_un_employe(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-TEST-02',
            'nom' => 'Mbappe',
            'prenom' => 'Alice',
            'poste' => 'Professeur',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $enseignant = Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'Mathématiques',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 18.0,
        ]);

        $this->assertNotNull($employe->fresh()->enseignant);
        $this->assertSame($employe->id, $enseignant->employe->id);
    }

    public function test_un_employe_ne_peut_pas_avoir_deux_fiches_enseignant(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-TEST-03',
            'nom' => 'Kuate',
            'prenom' => 'Marc',
            'poste' => 'Enseignant',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'Physique',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 15.0,
        ]);

        $this->expectException(QueryException::class);

        Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'Chimie',
            'statut_contractuel' => 'vacataire',
            'charge_horaire_hebdo' => 10.0,
        ]);
    }

    public function test_creation_enseignant_ne_cree_pas_une_deuxieme_identite_employe(): void
    {
        $compteEmployesAvant = Employe::query()->count();

        $employe = Employe::query()->create([
            'matricule' => 'EMP-TEST-04',
            'nom' => 'Nnomo',
            'prenom' => 'Claire',
            'poste' => 'Enseignante SVT',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'SVT',
            'statut_contractuel' => 'contractuel',
            'charge_horaire_hebdo' => 20.0,
        ]);

        $this->assertSame($compteEmployesAvant + 1, Employe::query()->count());
    }

    public function test_informations_communes_proviennent_de_employe(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-TEST-05',
            'nom' => 'Tchinda',
            'prenom' => 'Paul',
            'email' => 'paul.tchinda@ambassadors.test',
            'telephone' => '677112233',
            'poste' => 'Enseignant Anglais',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $enseignant = Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'Anglais',
            'statut_contractuel' => 'vacataire',
            'charge_horaire_hebdo' => 12.0,
        ]);

        $readService = app(EnseignantsPedagogiquesReadContract::class);
        $donnees = $readService->tous();

        $this->assertArrayHasKey($enseignant->id, $donnees);
        $this->assertSame('Paul Tchinda', $donnees[$enseignant->id]['nom']);
        $this->assertSame('EMP-TEST-05', $donnees[$enseignant->id]['matricule']);
        $this->assertTrue($donnees[$enseignant->id]['actif']);
    }

    public function test_employe_sans_user_peut_etre_enseignant(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-NO-USER',
            'nom' => 'Simo',
            'prenom' => 'Luc',
            'user_id' => null,
            'poste' => 'Enseignant EPS',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $enseignant = Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'EPS',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 18.0,
        ]);

        $this->assertNull($employe->user_id);
        $this->assertNotNull($enseignant->fresh());
        $this->assertSame($employe->id, $enseignant->employe_id);
    }

    public function test_role_spatie_non_requis_pour_identite_enseignant(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-NO-ROLE',
            'nom' => 'Ekani',
            'prenom' => 'Bertrand',
            'role_id' => null,
            'poste' => 'Enseignant Histoire',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $enseignant = Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'Histoire-Géo',
            'statut_contractuel' => 'contractuel',
            'charge_horaire_hebdo' => 18.0,
        ]);

        $this->assertNull($employe->role_id);
        $this->assertNotNull($enseignant->id);
    }

    public function test_suppression_enseignant_ne_casse_pas_employe(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-DEL-TEST',
            'nom' => 'Fotso',
            'prenom' => 'Eric',
            'poste' => 'Enseignant Informatique',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $enseignant = Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'Informatique',
            'statut_contractuel' => 'contractuel',
            'charge_horaire_hebdo' => 16.0,
        ]);

        $enseignant->delete();

        $this->assertDatabaseHas('employes', ['id' => $employe->id]);
        $this->assertDatabaseMissing('enseignants', ['id' => $enseignant->id]);
        $this->assertNull($employe->fresh()->enseignant);
    }
}
