<?php

namespace Tests\Feature\Socle;

use App\Models\User;
use App\Modules\Socle\Contracts\UtilisateurServiceInterface;
use App\Modules\Socle\Services\UtilisateurService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UtilisateurServiceTest extends TestCase
{
    use RefreshDatabase;

    private UtilisateurServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new UtilisateurService;
    }

    public function test_creer_compte_cree_user_et_retourne_identifiant(): void
    {
        $userId = $this->service->creerCompte([
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john.doe@example.com',
            'telephone' => '123456789',
        ]);

        $this->assertIsInt($userId);
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'john.doe@example.com',
            'nom' => 'Doe',
            'prenom' => 'John',
            'statut' => 'actif',
        ]);
    }

    public function test_creer_compte_empeche_doublon_email(): void
    {
        $this->service->creerCompte([
            'nom' => 'Doe',
            'prenom' => 'John',
            'email' => 'john.doe@example.com',
        ]);

        $this->expectException(\DomainException::class);

        $this->service->creerCompte([
            'nom' => 'Smith',
            'prenom' => 'Jane',
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_reactiver_compte_met_a_jour_statut_et_email(): void
    {
        $user = User::query()->create([
            'name' => 'Inactive User',
            'email' => 'old@example.com',
            'password' => 'secret',
            'statut' => 'inactif',
        ]);

        $this->service->reactiverCompte($user->id, 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.com',
            'statut' => 'actif',
        ]);
    }

    public function test_reactiver_compte_empeche_doublon_email(): void
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'occupied@example.com',
            'password' => 'secret',
        ]);

        $user = User::query()->create([
            'name' => 'User to reactivate',
            'email' => 'other@example.com',
            'password' => 'secret',
            'statut' => 'inactif',
        ]);

        $this->expectException(\DomainException::class);

        $this->service->reactiverCompte($user->id, 'occupied@example.com');
    }

    public function test_mettre_a_jour_identite_synchros_champs_et_valide_email(): void
    {
        $user = User::query()->create([
            'name' => 'Jean Dupont',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont@example.com',
            'password' => 'secret',
            'statut' => 'actif',
        ]);

        $this->service->mettreAJourIdentite($user->id, [
            'nom' => 'Durand',
            'prenom' => 'Jean-Paul',
            'email' => 'jp.durand@example.com',
            'telephone' => '690000000',
            'statut' => 'suspendu',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nom' => 'Durand',
            'prenom' => 'Jean-Paul',
            'name' => 'Durand Jean-Paul',
            'email' => 'jp.durand@example.com',
            'telephone' => '690000000',
            'statut' => 'suspendu',
        ]);
    }

    public function test_synchroniser_roles_remplace_et_stabilise_les_roles_utilisateur(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $roleEnseignant = Role::query()->where('name', 'Enseignant')->firstOrFail();
        $roleComptable = Role::query()->where('name', 'Comptable')->firstOrFail();

        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'roles.test@example.com',
            'password' => 'secret',
        ]);
        $user->assignRole('Enseignant');

        $this->service->synchroniserRoles($user->id, $roleComptable->id);

        $freshUser = $user->fresh();
        $this->assertFalse($freshUser->hasRole('Enseignant'));
        $this->assertTrue($freshUser->hasRole('Comptable'));

        // Idempotence et répétition
        $this->service->synchroniserRoles($user->id, 'Comptable');
        $this->assertSame(['Comptable'], $this->service->roles($user->id));
    }

    public function test_synchroniser_roles_leve_exception_si_role_inexistant(): void
    {
        $user = User::query()->create([
            'name' => 'Test User',
            'email' => 'invalid.role@example.com',
            'password' => 'secret',
        ]);

        $this->expectException(\DomainException::class);

        $this->service->synchroniserRoles($user->id, 'RoleInexistantXYZ');
    }

    public function test_synchroniser_roles_leve_exception_si_user_inexistant(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->synchroniserRoles(999999, 'Enseignant');
    }
}
