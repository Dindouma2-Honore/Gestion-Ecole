<?php

declare(strict_types=1);

namespace Tests\Feature\Finances;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Exceptions\PaiementDejaAnnuleException;
use App\Modules\Finances\Models\MouvementCaisse;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\InscriptionFinanceReadContract;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaiementIdempotenceConcurrenceTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;
    private Classe $classe;
    private Eleve $eleve;
    private Inscription $inscription;
    private User $comptable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $niveau = Niveau::create(['nom' => '6ème', 'code' => '6E', 'ordre' => 1]);
        $this->classe = Classe::create([
            'nom' => '6ème A',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
        ]);

        $this->eleve = Eleve::create([
            'nom' => 'Kombé',
            'prenom' => 'Paul',
            'matricule_permanent' => 'AMB-2026-001',
            'sexe' => 'M',
            'date_naissance' => '2015-05-10',
            'lieu_naissance' => 'Yaoundé',
            'statut' => 'inscrit',
        ]);

        $this->inscription = Inscription::create([
            'eleve_id' => $this->eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'statut' => 'active',
            'date_inscription' => now(),
        ]);

        FormatNumerotation::create([
            'type_document' => 'recu',
            'format' => 'REC-{{annee}}-{{seq:4}}',
            'prochain_numero' => 1,
        ]);

        $this->comptable = User::factory()->create(['statut' => 'actif']);
        $this->comptable->assignRole('Comptable');
        $this->actingAs($this->comptable);

        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_lot_11c_inscription_finance_read_contract(): void
    {
        $contract = app(InscriptionFinanceReadContract::class);

        $options = $contract->getElevesInscritsOptions($this->annee->id);
        $this->assertArrayHasKey($this->eleve->id, $options);
        $this->assertStringContainsString('Kombé Paul', $options[$this->eleve->id]);

        $info = $contract->getInfoInscription($this->inscription->id, $this->eleve->id, $this->annee->id);
        $this->assertNotNull($info);
        $this->assertSame($this->inscription->id, $info['id']);
        $this->assertSame($this->classe->id, $info['classe_id']);
        $this->assertSame($this->eleve->id, $info['eleve_id']);
        $this->assertSame($this->annee->id, $info['annee_scolaire_id']);
        $this->assertSame('6ème A', $info['classe']->nom);
        $this->assertSame('AMB-2026-001', $info['eleve']->matricule_permanent);
    }

    public function test_paiement_rollback_on_failure_and_retry(): void
    {
        $paiementService = app(PaiementServiceContract::class);
        $initialCount = Paiement::count();

        // Simuler une exception dans la transaction (ex: montant invalide ou erreur caisse)
        $caught = null;
        try {
            DB::transaction(function () use ($paiementService) {
                $paiementService->enregistrerPaiement($this->eleve->id, 10000, 'especes', null, [
                    'inscription_id' => $this->inscription->id,
                ]);
                throw new \RuntimeException('Erreur simulée caisse');
            });
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        // Aucun paiement ne doit être persisté (rollback complet)
        $this->assertSame($initialCount, Paiement::count());
        $this->assertSame(0, MouvementCaisse::count());

        // Le retry réussi après le rollback doit fonctionner normalement
        $paiementReussi = $paiementService->enregistrerPaiement($this->eleve->id, 15000, 'especes', null, [
            'inscription_id' => $this->inscription->id,
        ]);

        $this->assertNotNull($paiementReussi);
        $this->assertSame(15000.0, (float) $paiementReussi->montant);
        $this->assertSame('valide', $paiementReussi->statut);
        $this->assertSame($initialCount + 1, Paiement::count());
        $this->assertSame(1, MouvementCaisse::count());
    }

    public function test_annulation_paiement_deja_annule_throws_exception(): void
    {
        $paiementService = app(PaiementServiceContract::class);
        $paiement = $paiementService->enregistrerPaiement($this->eleve->id, 20000, 'especes', null, [
            'inscription_id' => $this->inscription->id,
        ]);

        $this->assertSame('valide', $paiement->statut);

        // Première annulation
        $paiementService->annulerPaiement($paiement->id, 'Erreur de saisie');
        $this->assertSame('annule', $paiement->fresh()->statut);

        // Seconde annulation (double contre-passation) doit être refusée
        $this->expectException(PaiementDejaAnnuleException::class);
        $paiementService->annulerPaiement($paiement->id, 'Seconde tentative');
    }

    public function test_paiement_et_mouvement_caisse_uniques(): void
    {
        $paiementService = app(PaiementServiceContract::class);
        $paiement = $paiementService->enregistrerPaiement($this->eleve->id, 50000, 'especes', null, [
            'inscription_id' => $this->inscription->id,
        ]);

        $this->assertSame(1, Paiement::where('id', $paiement->id)->count());
        $this->assertSame(1, MouvementCaisse::where('reference_id', $paiement->id)->count());
    }
}
