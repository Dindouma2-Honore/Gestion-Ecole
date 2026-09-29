<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\HabilitationServiceContract;
use App\Modules\Socle\Models\HabilitationUserFonctionnalite;
use Database\Seeders\HabilitationsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocleHabilitationsUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(HabilitationsSeeder::class);
    }

    public function test_une_habilitation_specifique_utilisateur_peut_etre_accordee_et_retiree(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');

        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Enseignant');

        $service = app(HabilitationServiceContract::class);

        $this->actingAs($fondateur);

        // Retrait spécifique
        $service->retirerModulePourUser($user, 'module.scolarite', 'Restriction individuelle de sécurité');

        $this->assertFalse($service->moduleActifPourUser($user, 'module.scolarite'));
        $this->assertDatabaseHas('habilitations_users_fonctionnalites', [
            'user_id' => $user->id,
            'actif' => false,
            'dernier_motif' => 'Restriction individuelle de sécurité',
        ]);

        // Ré-octroi spécifique
        $service->accorderModulePourUser($user, 'module.scolarite');

        $this->assertTrue($service->moduleActifPourUser($user, 'module.scolarite'));
        $this->assertDatabaseHas('habilitations_users_fonctionnalites', [
            'user_id' => $user->id,
            'actif' => true,
        ]);
    }
}
