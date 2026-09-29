<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Contracts\RecouvrementServiceContract;
use App\Modules\Finances\Filament\Resources\RelancePaiementResource;
use App\Modules\Finances\Models\EcheancierNegocie;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\PromessePaiement;
use App\Modules\Finances\Models\RelancePaiement;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Contracts\NotificationServiceContract;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeEleveService;
use Tests\Support\FakeNotificationService;
use Tests\TestCase;

class FinancesE42Test extends TestCase
{
    use RefreshDatabase;

    private RecouvrementServiceContract $recouvrement;

    private FakeNotificationService $notification;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);
        GrilleFrais::create([
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
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
            2 => ['nom' => 'Martin', 'prenom' => 'Paul', 'classe_id' => 1, 'niveau_id' => $niveau->id],
        ]));

        $this->notification = new FakeNotificationService;
        $this->app->instance(NotificationServiceContract::class, $this->notification);

        $this->recouvrement = app(RecouvrementServiceContract::class);
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_get_liste_debiteurs_only_lists_students_with_a_positive_balance(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        app(PaiementServiceContract::class)->enregistrerPaiement(1, 100000, 'bancaire');

        $debiteurs = $this->recouvrement->getListeDebiteurs();

        $this->assertCount(1, $debiteurs);
        $this->assertSame(2, $debiteurs->first()['eleve_id']);
        $this->assertSame(100000.0, $debiteurs->first()['reste_a_payer']);
    }

    public function test_traiter_relances_impayes_records_a_relance_and_sends_a_notification(): void
    {
        $this->recouvrement->traiterRelancesImpayes();

        $this->assertDatabaseHas('relances_paiement', [
            'eleve_id' => 1,
            'niveau_relance' => 1,
            'canal' => 'whatsapp',
        ]);
        $this->assertDatabaseHas('relances_paiement', [
            'eleve_id' => 2,
            'niveau_relance' => 1,
        ]);
        $this->assertCount(2, $this->notification->envoyees);
        $this->assertSame('whatsapp', $this->notification->envoyees[0]['canal']);
    }

    public function test_traiter_relances_impayes_does_not_repeat_within_fifteen_days(): void
    {
        RelancePaiement::create([
            'eleve_id' => 1,
            'niveau_relance' => 1,
            'canal' => 'whatsapp',
            'date_relance' => now()->subDays(5),
            'reste_a_payer_constate' => 100000,
        ]);

        $this->recouvrement->traiterRelancesImpayes();

        $this->assertSame(1, RelancePaiement::where('eleve_id', 1)->count());
    }

    public function test_traiter_relances_impayes_escalates_to_letter_after_third_relance(): void
    {
        RelancePaiement::create([
            'eleve_id' => 1,
            'niveau_relance' => 2,
            'canal' => 'whatsapp',
            'date_relance' => now()->subDays(20),
            'reste_a_payer_constate' => 100000,
        ]);

        $this->recouvrement->traiterRelancesImpayes();

        $this->assertDatabaseHas('relances_paiement', [
            'eleve_id' => 1,
            'niveau_relance' => 3,
            'canal' => 'lettre',
        ]);
        $envoi = collect($this->notification->envoyees)->firstWhere('eleve_id', 1);
        $this->assertSame('email', $envoi['canal']);
    }

    public function test_proposer_echeancier_negocie_creates_a_record(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);

        $echeancier = $this->recouvrement->proposerEcheancierNegocie(1, 100000, 4, now()->addDays(10));

        $this->assertInstanceOf(EcheancierNegocie::class, $echeancier);
        $this->assertSame('propose', $echeancier->statut);
        $this->assertSame($auteur->id, $echeancier->approuve_par);
    }

    public function test_enregistrer_promesse_creates_a_record(): void
    {
        $promesse = $this->recouvrement->enregistrerPromesse(1, now()->addDays(5), 50000);

        $this->assertInstanceOf(PromessePaiement::class, $promesse);
        $this->assertDatabaseHas('promesses_paiement', ['eleve_id' => 1, 'montant_promis' => 50000]);
    }

    public function test_get_taux_recouvrement_computes_the_percentage_paid(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        app(PaiementServiceContract::class)->enregistrerPaiement(1, 50000, 'bancaire');

        // Élève 1 doit 100000, a payé 50000. Élève 2 doit 100000, n'a rien payé.
        $taux = $this->recouvrement->getTauxRecouvrement($this->annee->id);

        $this->assertSame(25.0, $taux);
    }

    public function test_relance_resource_lists_relances(): void
    {
        $this->recouvrement->traiterRelancesImpayes();
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $this->get(RelancePaiementResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Jane Doe');
    }
}
