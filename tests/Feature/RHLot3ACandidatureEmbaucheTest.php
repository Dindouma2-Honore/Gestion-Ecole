<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Models\Candidature;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\PosteAdministratif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RHLot3ACandidatureEmbaucheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Comptable']);
        Role::firstOrCreate(['name' => 'SurveillantGeneral']);
    }

    public function test_candidature_acceptee_peut_etre_embauchee(): void
    {
        $role = Role::where('name', 'Comptable')->firstOrFail();
        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Comptable Principal',
            'actif' => true,
        ]);

        $candidature = Candidature::query()->create([
            'nom' => 'Mbarga',
            'prenom' => 'Joseph',
            'email' => 'joseph.mbarga@ambassadors.test',
            'telephone' => '690112233',
            'poste_souhaite' => 'Comptable Principal',
            'statut' => 'retenue',
        ]);

        $resultat = app(EmployeServiceContract::class)->embaucher([
            'candidature_id' => $candidature->id,
            'nom' => $candidature->nom,
            'prenom' => $candidature->prenom,
            'email' => $candidature->email,
            'telephone' => $candidature->telephone,
            'poste_administratif_id' => $posteAdmin->id,
            'role_id' => $role->id,
            'type_contrat' => 'CDI',
            'formule_paie' => 'fixe',
            'salaire_base' => 250000,
            'date_debut_contrat' => now()->toDateString(),
        ]);

        $employe = $resultat['employe'];
        $this->assertInstanceOf(Employe::class, $employe);
        $this->assertSame('Mbarga', $employe->nom);
        $this->assertSame('Joseph', $employe->prenom);
        $this->assertSame($posteAdmin->id, $employe->poste_administratif_id);
        $this->assertSame('Comptable Principal', $employe->poste);
        $this->assertNotNull($employe->user_id);

        $user = User::query()->findOrFail($employe->user_id);
        $this->assertTrue($user->hasRole('Comptable'));

        $this->assertSame('embauche', $candidature->fresh()->statut);
        $this->assertSame($employe->id, $candidature->fresh()->employe_id);
    }

    public function test_candidature_refusee_ne_peut_pas_etre_embauchee(): void
    {
        $role = Role::where('name', 'Comptable')->firstOrFail();

        $candidature = Candidature::query()->create([
            'nom' => 'Nguidjol',
            'prenom' => 'Pierre',
            'email' => 'pierre.nguidjol@ambassadors.test',
            'telephone' => '699887766',
            'poste_souhaite' => 'Comptable',
            'statut' => 'rejetee',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cette candidature n’est plus disponible pour une intégration.');

        app(EmployeServiceContract::class)->embaucher([
            'candidature_id' => $candidature->id,
            'nom' => $candidature->nom,
            'prenom' => $candidature->prenom,
            'email' => $candidature->email,
            'role_id' => $role->id,
            'type_contrat' => 'CDI',
            'formule_paie' => 'fixe',
            'salaire_base' => 200000,
            'date_debut_contrat' => now()->toDateString(),
        ]);
    }

    public function test_double_embauche_impossible(): void
    {
        $role = Role::where('name', 'Comptable')->firstOrFail();

        $candidature = Candidature::query()->create([
            'nom' => 'Bikoe',
            'prenom' => 'Jeanne',
            'email' => 'jeanne.bikoe@ambassadors.test',
            'telephone' => '677334455',
            'poste_souhaite' => 'Comptable',
            'statut' => 'retenue',
        ]);

        app(EmployeServiceContract::class)->embaucher([
            'candidature_id' => $candidature->id,
            'nom' => $candidature->nom,
            'prenom' => $candidature->prenom,
            'email' => $candidature->email,
            'role_id' => $role->id,
            'type_contrat' => 'CDI',
            'formule_paie' => 'fixe',
            'salaire_base' => 200000,
            'date_debut_contrat' => now()->toDateString(),
        ]);

        $this->assertSame('embauche', $candidature->fresh()->statut);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cette candidature n’est plus disponible pour une intégration.');

        app(EmployeServiceContract::class)->embaucher([
            'candidature_id' => $candidature->id,
            'nom' => $candidature->nom,
            'prenom' => $candidature->prenom,
            'email' => $candidature->email,
            'role_id' => $role->id,
            'type_contrat' => 'CDI',
            'formule_paie' => 'fixe',
            'salaire_base' => 200000,
            'date_debut_contrat' => now()->toDateString(),
        ]);
    }

    public function test_resolution_automatique_poste_administratif_depuis_candidature(): void
    {
        $role = Role::where('name', 'SurveillantGeneral')->firstOrFail();
        $posteAdmin = PosteAdministratif::query()->create([
            'nom' => 'Surveillant Général',
            'actif' => true,
        ]);

        $candidature = Candidature::query()->create([
            'nom' => 'Obama',
            'prenom' => 'Samuel',
            'email' => 'samuel.obama@ambassadors.test',
            'telephone' => '655443322',
            'poste_souhaite' => 'Surveillant Général',
            'statut' => 'retenue',
        ]);

        $resultat = app(EmployeServiceContract::class)->embaucher([
            'candidature_id' => $candidature->id,
            'nom' => $candidature->nom,
            'prenom' => $candidature->prenom,
            'email' => $candidature->email,
            'role_id' => $role->id,
            'type_contrat' => 'CDD',
            'formule_paie' => 'fixe',
            'salaire_base' => 180000,
            'date_debut_contrat' => now()->toDateString(),
        ]);

        $employe = $resultat['employe'];
        $this->assertSame($posteAdmin->id, $employe->poste_administratif_id);
        $this->assertSame('Surveillant Général', $employe->poste);
    }
}
