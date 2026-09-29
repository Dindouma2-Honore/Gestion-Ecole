<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Filament\Resources\CandidatureResource;
use App\Modules\RH\Filament\Resources\CandidatureResource\Pages\ListCandidatures;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use App\Modules\Socle\Filament\Resources\UserResource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RHRecrutementAutomatiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_embauche_cree_compte_dossier_historique_et_contrat_mixte(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::query()->where('name', 'Enseignant')->firstOrFail();

        $resultat = app(EmployeServiceContract::class)->embaucher([
            'nom' => 'Ngo', 'prenom' => 'Sarah', 'email' => 'sarah.ngo@ambassadors.test',
            'telephone' => '699000000', 'role_id' => $role->id,
            'date_embauche' => '2026-09-07',
            'responsabilite_fixe' => true, 'responsabilite_horaire' => true,
            'type_contrat' => 'CDI', 'date_debut_contrat' => '2026-09-07',
            'salaire_base' => 220000, 'taux_horaire' => 3500,
        ]);

        $employe = $resultat['employe']->fresh(['user', 'contrats', 'historiqueCarriere']);
        $this->assertMatchesRegularExpression('/^EMP-2026-\d{4}$/', $employe->matricule);
        $this->assertTrue($employe->user->must_change_password);
        $this->assertTrue($employe->user->hasRole('Enseignant'));
        $this->assertSame('Enseignant', $employe->poste);
        $this->assertSame('actif', $employe->statut);
        $this->assertSame('mixte', $employe->contrats->first()->categorie_paie);
        $this->assertSame('embauche', $employe->historiqueCarriere->first()->evenement);
    }

    public function test_recrutement_est_dans_rh_et_creation_utilisateur_est_retiree_de_l_administration(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $candidature = Candidature::query()->create([
            'nom' => 'Manga', 'prenom' => 'Luc', 'email' => 'luc.manga@example.test',
            'poste_souhaite' => 'Comptable',
        ]);

        $this->get(CandidatureResource::getUrl())->assertSuccessful();
        Livewire::test(ListCandidatures::class)->assertCanSeeTableRecords([$candidature]);
        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->assertFalse(UserResource::canCreate());
    }

    public function test_un_personnel_historique_peut_recevoir_un_acces_sans_etre_recree(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::query()->where('name', 'Comptable')->firstOrFail();
        $employe = Employe::query()->create([
            'matricule' => 'FACILG-ACCESS-01', 'nom' => 'Nana', 'prenom' => 'Paul',
            'poste' => 'Poste FACILG à reclasser', 'date_embauche' => '2020-09-01', 'statut' => 'actif',
        ]);

        $resultat = app(EmployeServiceContract::class)->creerAcces($employe->id, 'paul.nana@ambassadors.test', $role->id);

        $this->assertNotNull($resultat['mot_de_passe_temporaire']);
        $this->assertTrue($resultat['user']->must_change_password);
        $this->assertTrue($resultat['user']->hasRole('Comptable'));
        $this->assertSame($resultat['user']->id, $employe->fresh()->user_id);
        $this->assertSame('Comptable', $employe->fresh()->poste);
    }
}
