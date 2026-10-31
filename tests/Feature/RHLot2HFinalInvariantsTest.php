<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Filament\Resources\EmployeResource\Pages\EditEmploye;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\RH\Models\PosteAdministratif;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RHLot2HFinalInvariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_changement_statut_employe_ne_modifie_pas_implicitement_user_statut(): void
    {
        $userId = app(UtilisateurServiceInterface::class)->creerCompte([
            'email' => 'employe.statut@ambassadors.test',
            'name' => 'Jean Status',
            'nom' => 'Status',
            'prenom' => 'Jean',
            'statut' => 'actif',
        ]);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-STAT-01',
            'nom' => 'Status',
            'prenom' => 'Jean',
            'user_id' => $userId,
            'poste' => 'Comptable',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        EditEmploye::updatePersonnel($employe, [
            'nom' => 'Status',
            'prenom' => 'Jean',
            'statut' => 'en_conge',
        ]);

        $user = User::query()->findOrFail($userId);
        $this->assertSame('en_conge', $employe->fresh()->statut);
        $this->assertSame('actif', $user->statut);
    }

    public function test_changement_contrat_statut_ne_modifie_pas_implicitement_user_statut(): void
    {
        $userId = app(UtilisateurServiceInterface::class)->creerCompte([
            'email' => 'contrat.statut@ambassadors.test',
            'name' => 'Paul Contrat',
            'nom' => 'Contrat',
            'prenom' => 'Paul',
            'statut' => 'actif',
        ]);

        $employe = Employe::query()->create([
            'matricule' => 'EMP-STAT-02',
            'nom' => 'Contrat',
            'prenom' => 'Paul',
            'user_id' => $userId,
            'poste' => 'Informaticien',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contrat = Contrat::query()->create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'categorie_paie' => 'fixe',
            'salaire_base' => 200000,
            'date_debut' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $contrat->update(['statut' => 'termine']);

        $user = User::query()->findOrFail($userId);
        $this->assertSame('termine', $contrat->fresh()->statut);
        $this->assertSame('actif', $user->statut);
    }

    public function test_invariants_finaux_employe_et_enseignant(): void
    {
        $employe = Employe::query()->create([
            'matricule' => 'EMP-INV-01',
            'nom' => 'Kouam',
            'prenom' => 'David',
            'user_id' => null,
            'poste' => 'Gestionnaire',
            'date_embauche' => now()->toDateString(),
            'statut' => 'actif',
        ]);

        $this->assertNull($employe->user_id);
        $this->assertNull($employe->enseignant);

        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Responsable Logistique',
            'actif' => true,
        ]);

        EditEmploye::updatePersonnel($employe, [
            'nom' => 'Kouam',
            'prenom' => 'David',
            'poste_administratif_id' => $posteAdmin->id,
            'motif_changement_poste' => 'Reclassification annuelle',
        ]);

        $this->assertNull($employe->fresh()->user_id);
        $this->assertSame('Responsable Logistique', $employe->fresh()->poste);

        $enseignant = Enseignant::query()->create([
            'employe_id' => $employe->id,
            'specialite' => 'Logistique',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 10.0,
        ]);

        $this->assertSame($employe->id, $enseignant->employe->id);
        $this->assertSame($enseignant->id, $employe->fresh()->enseignant->id);
    }
}
