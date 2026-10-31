<?php

declare(strict_types=1);

namespace Tests\Feature\Scolarite;

use App\Models\User;
use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Exceptions\CapaciteClasseDepasseeException;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Models\TransfertAffectation;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\DocumentNumberingSeeder;
use Database\Seeders\GroupesFraisSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControleQuatreYeuxEtTransfertTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateur;

    private User $comptable;

    private User $secretaire;

    private AnneeScolaire $annee;

    private Classe $classeA;

    private Classe $classeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            DocumentNumberingSeeder::class,
            GroupesFraisSeeder::class,
        ]);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);

        $cycle = Cycle::create(['code' => 'PRI', 'nom' => 'Primaire', 'ordre' => 1]);
        $niveau = Niveau::create(['code' => 'CP', 'nom' => 'CP', 'cycle_id' => $cycle->id, 'ordre' => 1]);

        $this->classeA = Classe::create([
            'code' => 'CP-A',
            'nom' => 'CP A',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 2,
            'statut' => 'active',
            'frais' => 120_000,
        ]);

        $this->classeB = Classe::create([
            'code' => 'CP-B',
            'nom' => 'CP B',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 2,
            'statut' => 'active',
            'frais' => 150_000,
        ]);

        // Configuration financière Classe A (120 000 = 60 000 + 60 000)
        app(ConfigurationFraisClasseServiceContract::class)->configurer(
            $this->classeA->id,
            $this->annee->id,
            120_000,
            [
                ['ordre' => 1, 'libelle' => 'Tranche 1', 'montant' => 60_000, 'date_echeance' => '2026-10-31'],
                ['ordre' => 2, 'libelle' => 'Tranche 2', 'montant' => 60_000, 'date_echeance' => '2027-01-31'],
            ],
            ['politique_validation_inscription' => 'inscription_et_premiere_tranche']
        );

        // Configuration financière Classe B (150 000 = 70 000 + 80 000)
        app(ConfigurationFraisClasseServiceContract::class)->configurer(
            $this->classeB->id,
            $this->annee->id,
            150_000,
            [
                ['ordre' => 1, 'libelle' => 'Tranche 1', 'montant' => 70_000, 'date_echeance' => '2026-10-31'],
                ['ordre' => 2, 'libelle' => 'Tranche 2', 'montant' => 80_000, 'date_echeance' => '2027-01-31'],
            ],
            ['politique_validation_inscription' => 'inscription_et_premiere_tranche']
        );

        $this->fondateur = User::factory()->create(['email' => 'fondateur@ambassadors.com', 'statut' => 'actif']);
        $this->fondateur->assignRole('Fondateur');

        $this->comptable = User::factory()->create(['email' => 'comptable@ambassadors.com', 'statut' => 'actif']);
        $this->comptable->assignRole('Comptable');

        $this->secretaire = User::factory()->create(['email' => 'secretaire@ambassadors.com', 'statut' => 'actif']);
        $this->secretaire->assignRole('SurveillantGeneral');
    }

    public function test_controle_quatre_yeux_annulation_requiert_visa_comptable_puis_validation_fondateur(): void
    {
        $inscriptionService = app(InscriptionServiceInterface::class);
        $classeService = app(ClasseServiceInterface::class);

        $eleve = Eleve::create([
            'nom' => 'Kuate',
            'prenom' => 'Samuel',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Kuate Papa',
            'telephone_tuteur' => '677112233',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-KUA-4Y',
        ]);

        $inscriptionId = $inscriptionService->inscrire($eleve->id, $this->classeA->id, $this->annee->id);
        $this->assertEquals(1, $classeService->getPlacesRestantes($this->classeA->id));

        // 1. Demande d'annulation par un agent non-comptable
        $this->actingAs($this->secretaire);
        $inscriptionService->demanderAnnulation($inscriptionId, 'Demande de la famille pour raison médicale', $this->secretaire->id);

        $inscription = Inscription::findOrFail($inscriptionId);
        $this->assertEquals('en_attente_annulation', $inscription->statut);
        $this->assertFalse($inscription->aVisaComptable());
        $this->assertEquals($this->secretaire->id, $inscription->annulation_demandee_par);

        // 2. Le Fondateur tente de valider DIRECTEMENT sans visa comptable -> BLOCAGE STRICT
        $this->actingAs($this->fondateur);
        try {
            $inscriptionService->validerAnnulation($inscriptionId, 'Validation prématurée', $this->fondateur->id);
            $this->fail('La validation sans visa comptable aurait dû être rejetée.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('contrôle à 4 yeux', $e->getMessage());
        }

        // 3. Un agent non-comptable tente de poser le visa comptable -> BLOCAGE
        $this->actingAs($this->secretaire);
        try {
            $inscriptionService->viserAnnulation($inscriptionId, 'Visa pirate', $this->secretaire->id);
            $this->fail('Un non-comptable ne doit pas pouvoir poser le visa.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('Comptabilité', $e->getMessage());
        }

        // 4. La Comptable appose le visa financier (1er œil)
        $this->actingAs($this->comptable);
        $inscriptionService->viserAnnulation($inscriptionId, 'Versements vérifiés, quittance en règle', $this->comptable->id);

        $inscription->refresh();
        $this->assertTrue($inscription->aVisaComptable());
        $this->assertEquals($this->comptable->id, $inscription->annulation_visee_par);
        $this->assertEquals('Versements vérifiés, quittance en règle', $inscription->visa_comptable_notes);

        // 5. Le Fondateur valide définitivement l'annulation (2nd œil)
        $this->actingAs($this->fondateur);
        $inscriptionService->validerAnnulation($inscriptionId, 'Accord final de la direction', $this->fondateur->id);

        $inscription->refresh();
        $this->assertEquals('annulee', $inscription->statut);
        $this->assertEquals($this->fondateur->id, $inscription->annulation_validee_par);
        $this->assertNotNull($inscription->annulation_validee_le);

        // La place est libérée et l'élève redevient admis
        $this->assertEquals(2, $classeService->getPlacesRestantes($this->classeA->id));
        $this->assertEquals('admis', $eleve->fresh()->statut);
    }

    public function test_eleve_annule_quitte_la_liste_des_inscrits(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Mbida',
            'prenom' => 'Valentin',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Mbida Tuteur',
            'telephone_tuteur' => '699112233',
            'matricule_permanent' => 'AMB-TEST-MBI-01',
        ]);

        $inscriptionId = app(InscriptionServiceInterface::class)->inscrire($eleve->id, $this->classeA->id, $this->annee->id);

        // Avant annulation : présent dans l'onglet par défaut "Élèves inscrits"
        $listPage = new ListInscriptions;
        $tabs = $listPage->getTabs();
        $this->assertArrayHasKey('inscrits', $tabs);
        $this->assertArrayHasKey('annulees', $tabs);

        $queryInscritsAvant = Inscription::query();
        $tabs['inscrits']->modifyQuery($queryInscritsAvant);
        $this->assertTrue($queryInscritsAvant->where('id', $inscriptionId)->exists());

        // Annulation avec visa comptable et validation fondateur
        $this->actingAs($this->comptable);
        app(InscriptionServiceInterface::class)->demanderAnnulation($inscriptionId, 'Départ à l’étranger', $this->comptable->id);

        $this->actingAs($this->fondateur);
        app(InscriptionServiceInterface::class)->validerAnnulation($inscriptionId, 'Annulation confirmée', $this->fondateur->id);

        // Après annulation : l'élève a QUITTÉ la liste des élèves inscrits
        $queryInscritsApres = Inscription::query();
        $tabs['inscrits']->modifyQuery($queryInscritsApres);
        $this->assertFalse($queryInscritsApres->where('id', $inscriptionId)->exists());

        // Et l'élève figure bien dans l'onglet des annulées
        $queryAnnulees = Inscription::query();
        $tabs['annulees']->modifyQuery($queryAnnulees);
        $this->assertTrue($queryAnnulees->where('id', $inscriptionId)->exists());
    }

    public function test_transfert_de_classe_avec_simulation_comparative_et_recalcul_financier(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Abena',
            'prenom' => 'Franck',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Abena Parent',
            'telephone_tuteur' => '677889900',
            'matricule_permanent' => 'AMB-TEST-ABE-TR',
        ]);

        $parent = ParentTuteur::create([
            'nom' => 'Abena',
            'prenom' => 'Parent',
            'telephone' => '677889900',
        ]);

        $eleve->parentsTuteurs()->attach($parent->id, ['lien' => 'pere', 'responsable_paiement' => true]);

        // 1. Inscription en Classe A (Scolarité : 120 000 FCFA) avec versement de 45 000 FCFA
        // Affectation :
        // - 15 000 FCFA -> Frais d'inscription (soldé)
        // - 30 000 FCFA -> Tranche 1 (exigé 60 000, reste 30 000)
        // Solde restant total Classe A : 120 000 - 30 000 = 90 000 FCFA
        $inscriptionId = app(InscriptionServiceInterface::class)->demarrerPreinscription(
            eleveId: $eleve->id,
            classeId: $this->classeA->id,
            anneeScolaireId: $this->annee->id,
            parentId: $parent->id,
            fraisOptionnels: [],
            montantVerse: 45_000,
        );

        $facture = FacturePreinscription::where('inscription_id', $inscriptionId)->firstOrFail();
        app(FacturePreinscriptionServiceContract::class)->confirmerVersement($facture->id, 'especes');

        $classeService = app(ClasseServiceInterface::class);
        $this->assertEquals(1, $classeService->getPlacesRestantes($this->classeA->id));
        $this->assertEquals(2, $classeService->getPlacesRestantes($this->classeB->id));

        // 2. Simulation comparative avant transfert vers Classe B (Scolarité : 150 000 FCFA)
        $facturation = app(InscriptionFacturationPort::class);
        $simulation = $facturation->simulerTransfertFinancier($inscriptionId, $this->classeB->id);

        $this->assertEquals(120_000, $simulation['ancienne_classe']['scolarite']);
        $this->assertEquals(150_000, $simulation['nouvelle_classe']['scolarite']);
        $this->assertEquals(30_000, $simulation['difference']);
        $this->assertEquals(45_000, $simulation['total_paye']);
        $this->assertEquals(90_000, $simulation['ancienne_classe']['solde_restant']);
        // Nouveau solde restant Classe B : (15 000 inscription + 150 000 scolarité) - 45 000 payé = 120 000 FCFA
        $this->assertEquals(120_000, $simulation['nouvelle_classe']['solde_restant']);

        // Vérification de la projection des tranches
        $tranche1 = $simulation['tranches'][0];
        $this->assertEquals(70_000, $tranche1['montant']);
        $this->assertEquals(30_000, $tranche1['paye']);
        $this->assertEquals(40_000, $tranche1['reste']);
        $this->assertEquals('partiellement_payee', $tranche1['statut']);

        // 3. Exécution effective du transfert
        $transfert = app(InscriptionServiceInterface::class)->transfererClasse(
            inscriptionId: $inscriptionId,
            nouvelleClasseId: $this->classeB->id,
            motif: 'Option linguistique renforcée en CP B',
            dateTransfert: '2026-09-28',
        );

        $this->assertInstanceOf(TransfertAffectation::class, $transfert);
        $this->assertEquals($this->classeA->id, $transfert->classe_origine_id);
        $this->assertEquals($this->classeB->id, $transfert->classe_destination_id);
        $this->assertEquals(120_000, (float) $transfert->montant_scolarite_origine);
        $this->assertEquals(150_000, (float) $transfert->montant_scolarite_destination);
        $this->assertEquals(90_000, (float) $transfert->solde_avant_transfert);
        $this->assertEquals(120_000, (float) $transfert->solde_apres_transfert);

        // 4. Vérification des capacités des classes après transfert
        // Classe A libérée (2 places restantes), Classe B occupée (1 place restante)
        $this->assertEquals(2, $classeService->getPlacesRestantes($this->classeA->id));
        $this->assertEquals(1, $classeService->getPlacesRestantes($this->classeB->id));

        // Inscription rattachée à la classe B
        $inscription = Inscription::findOrFail($inscriptionId);
        $this->assertEquals($this->classeB->id, $inscription->classe_id);
        $this->assertEquals(120_000, $facturation->getResteAPayer($inscriptionId));
    }

    public function test_transfert_bloque_si_classe_destination_est_pleine(): void
    {
        $this->actingAs($this->fondateur);

        // Remplir la classe B (capacité 2)
        for ($i = 1; $i <= 2; $i++) {
            $e = Eleve::create([
                'nom' => "EleveB{$i}",
                'prenom' => 'Test',
                'sexe' => 'M',
                'statut' => 'admis',
                'nom_tuteur' => 'Tuteur',
                'telephone_tuteur' => '67700000'.$i,
            ]);
            app(InscriptionServiceInterface::class)->inscrire($e->id, $this->classeB->id, $this->annee->id);
        }

        $this->assertEquals(0, app(ClasseServiceInterface::class)->getPlacesRestantes($this->classeB->id));

        // Élève en classe A
        $eleveA = Eleve::create([
            'nom' => 'EleveA',
            'prenom' => 'Test',
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => 'Tuteur',
            'telephone_tuteur' => '677999888',
        ]);
        $inscriptionId = app(InscriptionServiceInterface::class)->inscrire($eleveA->id, $this->classeA->id, $this->annee->id);

        $this->expectException(CapaciteClasseDepasseeException::class);
        app(InscriptionServiceInterface::class)->transfererClasse($inscriptionId, $this->classeB->id, 'Tentative transfert classe saturée');
    }
}
