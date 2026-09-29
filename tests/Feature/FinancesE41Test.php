<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Exceptions\SessionCaisseDejaOuverteException;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource;
use App\Modules\Finances\Filament\Resources\MouvementCaisseResource\Pages\ListMouvementsCaisse;
use App\Modules\Finances\Filament\Resources\MouvementDiversResource\Pages\CreateMouvementDivers;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\MouvementCaisse;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\SessionCaisse;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Support\FakeEleveService;
use Tests\TestCase;

class FinancesE41Test extends TestCase
{
    use RefreshDatabase;

    private CaisseServiceContract $caisse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
        AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
        GrilleFrais::create([
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => AnneeScolaire::first()->id,
            'type_frais' => 'scolarite',
            'montant' => 100000,
        ]);
        FormatNumerotation::create([
            'type_document' => 'recu',
            'format' => 'REC-{{annee}}-{{seq:4}}',
            'prochain_numero' => 1,
        ]);

        $this->app->instance(EleveServiceInterface::class, new FakeEleveService([
            1 => ['nom' => 'Doe', 'prenom' => 'Jane', 'classe_id' => 1, 'niveau_id' => $niveau->id],
        ]));

        $this->caisse = app(CaisseServiceContract::class);
    }

    public function test_opening_a_second_session_the_same_day_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));

        $this->caisse->ouvrirSession(50000);

        $this->expectException(SessionCaisseDejaOuverteException::class);
        $this->caisse->ouvrirSession(0);
    }

    public function test_miscellaneous_movement_requires_reason_and_is_recorded_in_cash(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        $this->caisse->ouvrirSession(0);

        Livewire::test(CreateMouvementDivers::class)
            ->fillForm(['type' => 'encaissement', 'montant' => 25000, 'motif' => 'Don reçu pour la bibliothèque'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('mouvements_caisse', [
            'type' => 'encaissement', 'montant' => 25000,
            'justificatif' => 'Don reçu pour la bibliothèque',
            'module_origine' => 'Finances', 'sous_module' => 'Mouvements divers',
        ]);

        Livewire::test(CreateMouvementDivers::class)
            ->fillForm(['type' => 'decaissement', 'montant' => 5000, 'motif' => ''])
            ->call('create')
            ->assertHasFormErrors(['motif' => 'required']);
    }

    public function test_recording_a_movement_opens_the_daily_session_automatically(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        $this->caisse->enregistrerMouvement('encaissement', 5000);

        $this->assertDatabaseHas('sessions_caisse', ['date_session' => now()->startOfDay(), 'solde_ouverture' => 0, 'statut' => 'ouverte']);
        $this->assertDatabaseHas('mouvements_caisse', ['type' => 'encaissement', 'montant' => 5000]);
    }

    public function test_automatic_opening_carries_forward_the_last_cash_balance(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        SessionCaisse::create([
            'date_session' => now()->subDay(),
            'solde_ouverture' => 30000,
            'solde_cloture_theorique' => 48000,
            'solde_cloture_reel' => 47500,
            'ecart' => -500,
            'statut' => 'cloturee',
            'ouverte_par' => $user->id,
            'cloturee_par' => $user->id,
        ]);

        $session = $this->caisse->garantirSessionOuverte();

        $this->assertSame(47500.0, (float) $session->solde_ouverture);
        $this->assertSame('ouverte', $session->statut);
    }

    public function test_automatic_opening_uses_previous_theoretical_balance_when_not_closed(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $ancienne = SessionCaisse::create([
            'date_session' => now()->subDay(), 'solde_ouverture' => 20000,
            'statut' => 'ouverte', 'ouverte_par' => $user->id,
        ]);
        MouvementCaisse::create(['session_caisse_id' => $ancienne->id, 'type' => 'encaissement', 'montant' => 10000]);
        MouvementCaisse::create(['session_caisse_id' => $ancienne->id, 'type' => 'decaissement', 'montant' => 3000]);

        $session = $this->caisse->garantirSessionOuverte();

        $this->assertSame(27000.0, (float) $session->solde_ouverture);
    }

    public function test_solde_theorique_reflects_opening_balance_and_movements(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        $this->caisse->ouvrirSession(50000);

        $this->caisse->enregistrerMouvement('encaissement', 20000);
        $this->caisse->enregistrerMouvement('decaissement', 8000);

        $this->assertSame(62000.0, $this->caisse->getSoldeTheoriqueActuel());
    }

    public function test_cloture_computes_ecart_and_audits_it_when_non_zero(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $this->caisse->ouvrirSession(50000);
        $this->caisse->enregistrerMouvement('encaissement', 10000);

        $session = $this->caisse->cloturerSession(55000);

        $this->assertSame('cloturee', $session->statut);
        $this->assertSame(60000.0, (float) $session->solde_cloture_theorique);
        $this->assertSame(-5000.0, (float) $session->ecart);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => SessionCaisse::class,
            'subject_id' => $session->id,
            'causer_id' => $user->id,
        ]);
    }

    public function test_cash_payment_automatically_creates_a_caisse_movement(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        $this->caisse->ouvrirSession(0);

        $paiement = app(PaiementServiceContract::class)->enregistrerPaiement(1, 15000, 'especes');

        $this->assertDatabaseHas('mouvements_caisse', [
            'type' => 'encaissement',
            'montant' => 15000,
            'source_type' => Paiement::class,
            'source_id' => $paiement->id,
        ]);
        $this->assertSame(15000.0, $this->caisse->getSoldeTheoriqueActuel());
    }

    public function test_cash_payment_opens_the_daily_session_automatically(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));

        app(PaiementServiceContract::class)->enregistrerPaiement(1, 15000, 'especes');

        $this->assertDatabaseHas('sessions_caisse', ['date_session' => now()->startOfDay(), 'statut' => 'ouverte']);
        $this->assertDatabaseHas('mouvements_caisse', ['type' => 'encaissement', 'montant' => 15000]);
    }

    public function test_movement_cannot_be_updated_or_deleted(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        $this->caisse->ouvrirSession(0);
        $this->caisse->enregistrerMouvement('encaissement', 1000);
        $mouvement = MouvementCaisse::first();

        $this->expectException(LogicException::class);
        $mouvement->delete();
    }

    public function test_filament_automatically_opens_and_can_close_the_session(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        Livewire::test(ListMouvementsCaisse::class)->assertActionVisible('cloturer');

        $this->assertDatabaseHas('sessions_caisse', ['statut' => 'ouverte', 'solde_ouverture' => 0]);

        Livewire::test(ListMouvementsCaisse::class)
            ->callAction('cloturer', ['solde_reel' => 0]);

        $this->assertDatabaseHas('sessions_caisse', ['statut' => 'cloturee', 'solde_cloture_reel' => 0]);

        $this->get(MouvementCaisseResource::getUrl('index'))
            ->assertSuccessful();
    }
}
