<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Exceptions\SessionCaisseIntrouvableException;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource\Pages\ListMouvementsCaisse;
use App\Modules\Finances\Filament\Widgets\CaisseOverview;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\SessionCaisse;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CaisseClotureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
    }

    public function test_cloture_enregistre_comptage_et_ecart_sur_session_ancienne(): void
    {
        $service = app(CaisseServiceContract::class);
        $this->travelTo(now()->subDay()->setTime(9, 0));
        $session = $service->ouvrirSession(1000);
        $service->enregistrerMouvement('encaissement', 500);
        $service->enregistrerMouvement('decaissement', 200);
        $this->travelBack();
        Livewire::test(ListMouvementsCaisse::class)->mountAction('cloturer')->fillForm(['solde_reel' => 1200])->callMountedAction()->assertHasNoActionErrors();
        $session->refresh();
        $this->assertSame('cloturee', $session->statut);
        $this->assertEquals(1300, $session->solde_cloture_theorique);
        $this->assertEquals(1200, $session->solde_cloture_reel);
        $this->assertEquals(-100, $session->ecart);
        $this->assertEquals(auth()->id(), $session->cloturee_par);
        $this->assertSame(1, SessionCaisse::count());
    }

    public function test_consulter_la_caisse_ne_cree_pas_de_session(): void
    {
        Livewire::test(ListMouvementsCaisse::class)->assertSuccessful();
        $this->assertSame(0, SessionCaisse::count());
    }

    public function test_cloture_du_jour_ne_reouvre_pas_et_report_utilise_le_solde_physique(): void
    {
        $service = app(CaisseServiceContract::class);
        $service->ouvrirSession(1000);
        $service->cloturerSession(900);
        Livewire::test(ListMouvementsCaisse::class)->assertActionHidden('cloturer')->assertActionHidden('ouvrir');
        $this->assertSame(1, SessionCaisse::count());
        $this->travel(1)->days();
        $next = $service->garantirSessionOuverte();
        $this->assertEquals(900, $next->solde_ouverture);
    }

    public function test_aucune_ecriture_apres_cloture_du_jour(): void
    {
        $service = app(CaisseServiceContract::class);
        $service->ouvrirSession(1000);
        $service->cloturerSession(1000);
        $this->expectException(SessionCaisseIntrouvableException::class);
        $service->enregistrerMouvement('encaissement', 100);
    }

    public function test_rapprochement_affiche_et_conserve_un_excedent(): void
    {
        $service = app(CaisseServiceContract::class);
        $service->ouvrirSession(1000);
        Livewire::test(ListMouvementsCaisse::class)->mountAction('cloturer')->fillForm(['solde_reel' => 1100])
            ->assertMountedActionModalSee('Excédent : 100 FCFA')->callMountedAction()->assertHasNoActionErrors();
        Livewire::test(CaisseOverview::class)
            ->assertSee('1 100 FCFA')->assertSee('Excédent');
        $this->assertEquals(100, SessionCaisse::first()->ecart);
    }

    public function test_rapprochement_physique_exclut_banque_et_mobile_sans_effacer_le_journal(): void
    {
        $annee = AnneeScolaire::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active']);
        $service = app(CaisseServiceContract::class);
        $service->ouvrirSession(1000);
        foreach (['especes', 'bancaire', 'mobile_money'] as $mode) {
            $paiement = Paiement::create(['numero_recu' => 'TEST-'.$mode, 'eleve_id' => 1, 'annee_scolaire_id' => $annee->id, 'montant' => 500, 'mode' => $mode, 'statut' => 'valide', 'encaisse_par' => auth()->id()]);
            $service->enregistrerMouvement('encaissement', 500, $paiement);
        }
        $this->assertEquals(1500, $service->getSoldeTheoriqueActuel());
        $session = $service->cloturerSession(1500);
        $this->assertEquals(0, $session->ecart);
        $this->assertEquals(1500, $session->mouvements()->sum('montant'));
        $this->assertSame(3, $session->mouvements()->count());
    }
}
