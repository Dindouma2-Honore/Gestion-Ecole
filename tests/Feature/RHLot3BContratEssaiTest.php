<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Contracts\ContratServiceInterface;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RHLot3BContratEssaiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employe_recroit_un_contrat_initial_valide(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-CT-01',
            'nom' => 'Atangana',
            'prenom' => 'Simon',
            'poste' => 'Comptable',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 250000,
            'date_debut' => now()->toDateString(),
            'periode_essai_fin' => now()->addMonths(3)->toDateString(),
            'statut' => 'actif',
        ]);

        $employe->update(['contrat_id' => $contrat->id]);

        $this->assertSame($employe->id, $contrat->employe_id);
        $this->assertSame($contrat->id, $employe->fresh()->contrat_id);
        $this->assertSame('250000.00', (string) $contrat->salaire_base);
        $this->assertSame('actif', $contrat->statut);
        $this->assertNotNull($contrat->periode_essai_fin);
    }

    public function test_modes_de_remuneration_non_incoherents(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-CT-02',
            'nom' => 'Onana',
            'prenom' => 'Fabrice',
            'poste' => 'Vacataire Informatique',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contratHoraire = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'Prestataire',
            'categorie_paie' => 'horaire',
            'salaire_base' => 0,
            'taux_horaire' => 5000,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->assertSame('5000.00', (string) $contratHoraire->taux_horaire);
        $this->assertSame('0.00', (string) $contratHoraire->salaire_base);

        $contratMixte = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDD',
            'categorie_paie' => 'mixte',
            'salaire_base' => 150000,
            'taux_horaire' => 3000,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->assertSame('150000.00', (string) $contratMixte->salaire_base);
        $this->assertSame('3000.00', (string) $contratMixte->taux_horaire);
    }

    public function test_periode_d_essai_coherente_et_consultable(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-CT-03',
            'nom' => 'Eyenga',
            'prenom' => 'Chantal',
            'poste' => 'Assistante RH',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $dateFinEssai = now()->addMonths(2)->toDateString();
        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDD',
            'categorie_paie' => 'fixe',
            'salaire_base' => 180000,
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addYear()->toDateString(),
            'periode_essai_fin' => $dateFinEssai,
            'statut' => 'actif',
        ]);

        $this->assertSame($dateFinEssai, $contrat->periode_essai_fin->toDateString());
    }

    public function test_transitions_contractuelles_valides(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-CT-04',
            'nom' => 'Nzie',
            'prenom' => 'Gaston',
            'poste' => 'Surveillant',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contrat = app(ContratServiceInterface::class)->creerContrat([
            'employe_id' => $employe->id,
            'type' => 'CDD',
            'categorie_paie' => 'fixe',
            'salaire_base' => 160000,
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addMonths(6)->toDateString(),
        ]);

        $this->assertTrue(app(ContratServiceInterface::class)->estActif($contrat->id));

        app(ContratServiceInterface::class)->resilier($contrat->id, now(), 'Démission acceptée');

        $contratFrais = $contrat->fresh();
        $this->assertSame('resilie', $contratFrais->statut);
        $this->assertFalse(app(ContratServiceInterface::class)->estActif($contrat->id));
    }

    public function test_statut_contrat_ne_modifie_pas_user_statut(): void
    {
        $userId = app(UtilisateurServiceInterface::class)->creerCompte([
            'email' => 'user.contrat.statut@ambassadors.test',
            'name' => 'Alain Biya',
            'nom' => 'Biya',
            'prenom' => 'Alain',
            'statut' => 'actif',
        ]);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-CT-05',
            'nom' => 'Biya',
            'prenom' => 'Alain',
            'user_id' => $userId,
            'poste' => 'Technicien',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 220000,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contrat->update(['statut' => 'resilie']);

        $user = User::query()->findOrFail($userId);
        $this->assertSame('resilie', $contrat->fresh()->statut);
        $this->assertSame('actif', $user->statut);
    }

    public function test_employe_sans_user_peut_avoir_un_contrat(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-CT-NO-USER',
            'nom' => 'Fouda',
            'prenom' => 'Bernadette',
            'user_id' => null,
            'poste' => 'Agent de maintenance',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 140000,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->assertNull($employe->user_id);
        $this->assertNotNull($contrat->id);
        $this->assertSame($employe->id, $contrat->employe_id);
    }
}
