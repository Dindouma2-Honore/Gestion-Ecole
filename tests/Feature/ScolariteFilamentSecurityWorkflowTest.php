<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Filament\Pages\SituationFinanciere;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScolariteFilamentSecurityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_profils_financiers_peuvent_lire_et_imprimer_la_situation_financiere(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);

        $this->get(SituationFinanciere::getUrl())
            ->assertSuccessful()
            ->assertSee('Situation financière des élèves')
            ->assertSee('Exporter PDF');

        Livewire::test(SituationFinanciere::class)
            ->assertSuccessful()
            ->call('exporterPdf')
            ->assertFileDownloaded('situation-financiere-'.today()->format('Y-m-d').'.pdf');
    }

    public function test_profil_parent_est_refuse_cote_serveur_pour_la_situation_financiere(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Parent');
        $this->actingAs($user);

        $this->get(SituationFinanciere::getUrl())->assertForbidden();
    }
}
