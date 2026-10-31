<?php

declare(strict_types=1);

namespace Tests\Feature\Scolarite;

use App\Models\User;
use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ViewInscription;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\DocumentNumberingSeeder;
use Database\Seeders\GroupesFraisSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InscriptionDetailsEtAnnulationTest extends TestCase
{
    use RefreshDatabase;

    private User $fondateur;

    private AnneeScolaire $annee;

    private Classe $classe;

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

        $this->classe = Classe::create([
            'code' => 'CP-A',
            'nom' => 'CP A',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 2,
            'statut' => 'active',
            'frais' => 125_000,
        ]);

        app(ConfigurationFraisClasseServiceContract::class)->configurer(
            $this->classe->id,
            $this->annee->id,
            125_000,
            [
                ['ordre' => 1, 'libelle' => 'Tranche 1', 'montant' => 50_000, 'date_echeance' => '2026-10-31'],
                ['ordre' => 2, 'libelle' => 'Tranche 2', 'montant' => 75_000, 'date_echeance' => '2027-01-31'],
            ],
            ['politique_validation_inscription' => 'inscription_et_premiere_tranche']
        );

        $this->fondateur = User::factory()->create([
            'email' => 'admin@ambassadors.com',
            'statut' => 'actif',
        ]);
        $this->fondateur->assignRole('Fondateur');
    }

    public function test_empeche_double_inscription_du_meme_eleve_dans_modal(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Ngo',
            'prenom' => 'Jeanne',
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => 'Ngo Papa',
            'telephone_tuteur' => '677112233',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-NGO-01',
        ]);

        // Première inscription réussie
        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(1, Inscription::where('eleve_id', $eleve->id)->where('annee_scolaire_id', $this->annee->id)->count());

        // Seconde tentative d'inscription pour la même année -> rejetée par validation
        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasActionErrors(['eleve_id']);
    }

    public function test_annulation_inscription_libere_place_et_reinitialise_statut_eleve(): void
    {
        $classeService = app(ClasseServiceInterface::class);
        $inscriptionService = app(InscriptionServiceInterface::class);

        $this->assertEquals(2, $classeService->getPlacesRestantes($this->classe->id));

        $eleve = Eleve::create([
            'nom' => 'Bella',
            'prenom' => 'Paul',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Bella Tuteur',
            'telephone_tuteur' => '699223344',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-BEL-01',
        ]);

        $inscriptionId = $inscriptionService->inscrire($eleve->id, $this->classe->id, $this->annee->id);
        $this->assertEquals(1, $classeService->getPlacesRestantes($this->classe->id));
        $this->assertEquals('inscrit', $eleve->fresh()->statut);

        // Annulation de l'inscription
        $inscriptionService->annulerInscription($inscriptionId, 'Déménagement de la famille dans une autre région');

        $inscription = Inscription::findOrFail($inscriptionId);
        $this->assertEquals('annulee', $inscription->statut);
        $this->assertEquals('admis', $eleve->fresh()->statut);
        $this->assertEquals(2, $classeService->getPlacesRestantes($this->classe->id));
        $this->assertFalse($inscriptionService->estInscrit($eleve->id, $this->annee->id));

        // Vérification de l'audit
        $historique = app(AuditServiceContract::class)->getHistorique($inscription);
        $actionAnnulation = $historique->first(fn ($act) => str_contains((string) $act->description, 'Annulation'));
        $this->assertNotNull($actionAnnulation);
        $this->assertEquals('Déménagement de la famille dans une autre région', $actionAnnulation->properties['motif'] ?? null);
    }

    public function test_annulation_supprime_facture_provisoire_impayee(): void
    {
        $inscriptionService = app(InscriptionServiceInterface::class);

        $eleve = Eleve::create([
            'nom' => 'Onana',
            'prenom' => 'Lucas',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Onana Parent',
            'telephone_tuteur' => '688334455',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-ONA-01',
        ]);

        $inscriptionId = $inscriptionService->inscrire($eleve->id, $this->classe->id, $this->annee->id);

        $facture = FacturePreinscription::create([
            'reference' => 'PRE-TEST-001',
            'inscription_id' => $inscriptionId,
            'eleve_id' => $eleve->id,
            'parent_id' => 1,
            'annee_scolaire_id' => $this->annee->id,
            'eleve_nom' => 'Lucas Onana',
            'parent_nom' => 'Onana Parent',
            'montant_total' => 15000,
            'montant_versement_prevu' => 15000,
            'statut' => 'en_attente_versement',
        ]);

        $this->assertNotNull($facture);
        $this->assertEquals('en_attente_versement', $facture->statut);

        // Annulation
        $inscriptionService->annulerInscription($inscriptionId, 'Abandon du dossier');

        $this->assertNull(FacturePreinscription::find($facture->id));
    }

    public function test_eleve_peut_etre_reinscrit_apres_annulation(): void
    {
        $inscriptionService = app(InscriptionServiceInterface::class);

        $eleve = Eleve::create([
            'nom' => 'Kuate',
            'prenom' => 'Eric',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Kuate Parent',
            'telephone_tuteur' => '655443322',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-KUA-01',
        ]);

        $id1 = $inscriptionService->inscrire($eleve->id, $this->classe->id, $this->annee->id);
        $inscriptionService->annulerInscription($id1, 'Erreur de saisie initiale');

        // Réinscription possible sans exception
        $id2 = $inscriptionService->inscrire($eleve->id, $this->classe->id, $this->annee->id);
        $this->assertEquals($id1, $id2);
        $this->assertTrue($inscriptionService->estInscrit($eleve->id, $this->annee->id));
        $this->assertEquals('inscrit', $eleve->fresh()->statut);
    }

    public function test_dossier_financier_complet_details_frais_et_scolarite(): void
    {
        $this->actingAs($this->fondateur);

        app(InscriptionFacturationPort::class)->assurerFraisDiversParDefaut();
        $fraisTenue = CatalogueFraisDivers::where('nom', 'Tenue scolaire')->firstOrFail();

        $eleve = Eleve::create([
            'nom' => 'Ewondo',
            'prenom' => 'Samuel',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Ewondo Joseph',
            'telephone_tuteur' => '677990011',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-EWO-01',
        ]);

        // Inscription avec frais divers Tenue (25 000) et versement de 50 000 FCFA
        // Affectation attendue:
        // - 15 000 -> Inscription (soldé)
        // - 25 000 -> Tenue scolaire (soldé)
        // - 10 000 surplus -> Tranche 1 scolarité (partiel: 10 000 / 50 000, reste 40 000)
        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'frais_optionnels' => ['divers-'.$fraisTenue->id],
                'montant_verse' => 50000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasNoActionErrors();

        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();

        $dossier = app(InscriptionFacturationPort::class)->getDossierFinancierComplet($inscription->id);

        // 1. Structure générale
        $this->assertArrayHasKey('totaux', $dossier);
        $this->assertArrayHasKey('inscription', $dossier);
        $this->assertArrayHasKey('frais_divers', $dossier);
        $this->assertArrayHasKey('scolarite', $dossier);
        $this->assertArrayHasKey('recu_dernier', $dossier);

        // 2. KPIs
        // Total = 15 000 (inscr) + 25 000 (tenue) + 125 000 (scol) = 165 000
        $this->assertEquals(165000, $dossier['totaux']['total_annuel']);
        $this->assertEquals(50000, $dossier['totaux']['total_paye']);
        $this->assertEquals(115000, $dossier['totaux']['reste_global']);
        $this->assertFalse($dossier['totaux']['est_solde']);

        // 3. Frais d'inscription
        $this->assertEquals(15000, $dossier['inscription']['montant']);
        $this->assertEquals(15000, $dossier['inscription']['paye']);
        $this->assertEquals(0, $dossier['inscription']['reste']);
        $this->assertEquals('couvert', $dossier['inscription']['statut']);

        // 4. Frais divers
        $this->assertCount(1, $dossier['frais_divers']['items']);
        $this->assertEquals('Tenue scolaire', $dossier['frais_divers']['items'][0]['nom']);
        $this->assertEquals(25000, $dossier['frais_divers']['items'][0]['montant']);
        $this->assertEquals(25000, $dossier['frais_divers']['items'][0]['paye']);
        $this->assertEquals(0, $dossier['frais_divers']['items'][0]['reste']);
        $this->assertEquals('couvert', $dossier['frais_divers']['items'][0]['statut']);

        // 5. Scolarité par tranches
        $this->assertEquals(125000, $dossier['scolarite']['montant_total']);
        $this->assertEquals(10000, $dossier['scolarite']['total_paye']);
        $this->assertEquals(115000, $dossier['scolarite']['reste_a_payer']);

        $tranches = $dossier['scolarite']['tranches'];
        $this->assertCount(2, $tranches);
        $this->assertEquals('Tranche 1', $tranches[0]['libelle']);
        $this->assertEquals(50000, $tranches[0]['montant']);
        $this->assertEquals(10000, $tranches[0]['paye']);
        $this->assertEquals(40000, $tranches[0]['reste']);
        $this->assertEquals('partiellement_payee', $tranches[0]['statut']);

        $this->assertEquals('Tranche 2', $tranches[1]['libelle']);
        $this->assertEquals(75000, $tranches[1]['montant']);
        $this->assertEquals(0, $tranches[1]['paye']);
        $this->assertEquals(75000, $tranches[1]['reste']);
        $this->assertEquals('a_venir', $tranches[1]['statut']);

        // 6. Versements / Reçus
        $this->assertCount(1, $dossier['versements']);
        $this->assertEquals(50000, $dossier['versements'][0]['montant']);
        $this->assertNotNull($dossier['recu_dernier']);
        $this->assertNotEmpty($dossier['recu_dernier']['contenu']);
    }

    public function test_vue_details_inscription_affiche_le_dashboard_financier(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Biloa',
            'prenom' => 'Chantal',
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => 'Biloa Marie',
            'telephone_tuteur' => '677884422',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-BIL-01',
        ]);

        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasNoActionErrors();

        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();

        // Test rendering the ViewInscription page
        Livewire::test(ViewInscription::class, ['record' => $inscription->id])
            ->assertSuccessful()
            ->assertSee('Biloa Chantal')
            ->assertSee('CP A')
            ->assertSee('Total annuel dû')
            ->assertSee('Total encaissé / réglé')
            ->assertSee('Solde restant à payer')
            ->assertSee('Échéancier de la scolarité');
    }

    public function test_action_annuler_dans_vue_inscription(): void
    {
        $comptable = User::factory()->create(['email' => 'comptable-manga@ambassadors.com', 'statut' => 'actif']);
        $comptable->assignRole('Comptable');

        $this->actingAs($comptable);

        $eleve = Eleve::create([
            'nom' => 'Manga',
            'prenom' => 'Serge',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Manga Guy',
            'telephone_tuteur' => '699001122',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-MAN-01',
        ]);

        $inscriptionId = app(InscriptionServiceInterface::class)->inscrire($eleve->id, $this->classe->id, $this->annee->id);

        // 1. La comptable initie l'annulation dans la vue
        Livewire::test(ViewInscription::class, ['record' => $inscriptionId])
            ->callAction('demanderAnnulation', [
                'motif' => 'Erreur administrative lors de la sélection de la classe',
            ])
            ->assertHasNoActionErrors();

        $inscription = Inscription::findOrFail($inscriptionId);
        $this->assertEquals('en_attente_annulation', $inscription->statut);
        $this->assertTrue($inscription->aVisaComptable());

        // 2. Le Fondateur valide définitivement l'annulation
        $this->actingAs($this->fondateur);

        Livewire::test(ViewInscription::class, ['record' => $inscriptionId])
            ->callAction('validerAnnulation', [
                'motif' => 'Annulation validée par la Direction',
            ])
            ->assertHasNoActionErrors();

        $inscription->refresh();
        $this->assertEquals('annulee', $inscription->statut);
        $this->assertEquals('admis', $eleve->fresh()->statut);
    }

    public function test_recu_pdf_est_strictement_homogene_avec_les_details_de_l_inscription(): void
    {
        $this->actingAs($this->fondateur);

        app(InscriptionFacturationPort::class)->assurerFraisDiversParDefaut();
        $fraisTenue = CatalogueFraisDivers::where('nom', 'Tenue scolaire')->firstOrFail();

        $eleve = Eleve::create([
            'nom' => 'Tchinda',
            'prenom' => 'Arnaud',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Tchinda Pierre',
            'telephone_tuteur' => '677001122',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-HOM-01',
        ]);

        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'frais_optionnels' => ['divers-'.$fraisTenue->id],
                'montant_verse' => 50000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasNoActionErrors();

        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();

        // 1. Tester la route d'impression du reçu
        $response = $this->get(route('impressions.inscriptions.recu', ['inscription' => $inscription->id]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // 2. Tester avec paramètre ?paiement=
        $versements = app(InscriptionFacturationPort::class)->getVersements($inscription->id);
        $this->assertNotEmpty($versements);
        $paiementId = $versements[0]['paiement_id'];

        $responsePaiement = $this->get(route('impressions.inscriptions.recu', [
            'inscription' => $inscription->id,
            'paiement' => $paiementId,
        ]));
        $responsePaiement->assertStatus(200);
        $responsePaiement->assertHeader('Content-Type', 'application/pdf');

        // 3. Vérifier le contenu du reçu PDF
        $recu = app(InscriptionFacturationPort::class)->getRecuDernierVersement($inscription->id);
        $this->assertNotNull($recu);
        $pdfContent = $recu['contenu'];
        $this->assertNotEmpty($pdfContent);

        // 4. Vérifier la vue Blade du reçu directement pour les éléments textuels
        $html = view('finances::pdf.recu-paiement', [
            'paiement' => Paiement::findOrFail($paiementId),
            'eleve' => app(EleveServiceInterface::class)->getEleve($eleve->id),
            'annee' => $this->annee,
            'contexte' => [
                'parent_nom' => 'Tchinda Pierre',
                'parent_telephone' => '677001122',
                'classe_nom' => 'CP A',
                'matricule' => 'AMB-TEST-HOM-01',
                'annee_libelle' => '2026-2027',
                'montant_total' => 165000.0,
                'total_paye' => 50000.0,
                'solde_restant' => 115000.0,
                'est_solde' => false,
                'repartition' => [
                    ['libelle' => "Frais d'inscription", 'montant' => 15000.0],
                    ['libelle' => 'Tenue scolaire', 'montant' => 25000.0],
                    ['libelle' => 'Scolarité — Tranche 1', 'montant' => 10000.0],
                ],
            ],
        ])->render();

        $decodedHtml = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        // Exacte homogénéité avec la fiche d'inscription
        $this->assertStringContainsString('Tchinda Arnaud', $decodedHtml);
        $this->assertStringContainsString('AMB-TEST-HOM-01', $decodedHtml);
        $this->assertStringContainsString('CP A', $decodedHtml);
        $this->assertStringContainsString('2026-2027', $decodedHtml);
        $this->assertStringContainsString('Tchinda Pierre', $decodedHtml);
        $this->assertStringContainsString('677001122', $decodedHtml);
        $this->assertStringContainsString("Frais d'inscription", $decodedHtml);
        $this->assertStringContainsString('Tenue scolaire', $decodedHtml);
        $this->assertStringContainsString('Scolarité — Tranche 1', $decodedHtml);
        $this->assertStringContainsString('165 000 FCFA', $decodedHtml); // Total annuel dû
        $this->assertStringContainsString('50 000 FCFA', $decodedHtml);  // Total cumulé réglé
        $this->assertStringContainsString('115 000 FCFA', $decodedHtml); // Solde restant annuel
    }

    public function test_non_fondateur_ne_peut_que_demander_l_annulation_et_fondateur_doit_la_valider(): void
    {
        $secretaire = User::factory()->create([
            'email' => 'secretaire@ambassadors.com',
            'statut' => 'actif',
        ]);
        $secretaire->assignRole('Comptable');

        $eleve = Eleve::create([
            'nom' => 'Biya',
            'prenom' => 'Junior',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Biya Senior',
            'telephone_tuteur' => '677223344',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-WF-01',
        ]);

        $inscriptionId = app(InscriptionServiceInterface::class)->inscrire($eleve->id, $this->classe->id, $this->annee->id);
        $inscription = Inscription::findOrFail($inscriptionId);

        // 1. Un non-fondateur demande l'annulation
        $this->actingAs($secretaire);

        Livewire::test(ViewInscription::class, ['record' => $inscriptionId])
            ->callAction('demanderAnnulation', [
                'motif' => 'Déménagement familial à Douala',
            ])
            ->assertHasNoActionErrors();

        $inscription->refresh();
        $this->assertEquals('en_attente_annulation', $inscription->statut);
        $this->assertEquals('Déménagement familial à Douala', $inscription->motif_annulation);
        $this->assertEquals($secretaire->id, $inscription->annulation_demandee_par);
        $this->assertNotNull($inscription->annulation_demandee_le);

        // La place reste réservée tant que le Fondateur n'a pas validé
        $this->assertEquals(1, app(ClasseServiceInterface::class)->getPlacesRestantes($this->classe->id));

        // Le non-fondateur ne peut pas forcer la validation
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Seul le Fondateur est habilité à valider l’annulation d’une inscription.');
        app(InscriptionServiceInterface::class)->validerAnnulation($inscriptionId);
    }

    public function test_fondateur_valide_l_annulation_libere_place_et_reinitialise_statut_eleve(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Koffi',
            'prenom' => 'Boris',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Koffi Paul',
            'telephone_tuteur' => '677334455',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-KOF-01',
        ]);

        $inscriptionId = app(InscriptionServiceInterface::class)->inscrire($eleve->id, $this->classe->id, $this->annee->id);
        $inscriptionService = app(InscriptionServiceInterface::class);

        // 1. Demande d'annulation
        $inscriptionService->demanderAnnulation($inscriptionId, 'Souhait des parents');
        $this->assertEquals('en_attente_annulation', Inscription::findOrFail($inscriptionId)->statut);

        // 2. Visa préalable de la Comptabilité (1er œil)
        $comptable = User::factory()->create(['email' => 'comptable-koffi@ambassadors.com', 'statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($comptable);
        Livewire::test(ViewInscription::class, ['record' => $inscriptionId])
            ->callAction('viserAnnulation', [
                'notes' => 'Versements vérifiés, visa accordé',
            ])
            ->assertHasNoActionErrors();

        // 3. Le Fondateur valide définitivement l'annulation (2e œil)
        $this->actingAs($this->fondateur);

        Livewire::test(ViewInscription::class, ['record' => $inscriptionId])
            ->callAction('validerAnnulation', [
                'motif' => 'Accordé par le Fondateur : Souhait des parents',
            ])
            ->assertHasNoActionErrors();

        $inscription = Inscription::findOrFail($inscriptionId);
        $this->assertEquals('annulee', $inscription->statut);
        $this->assertEquals($this->fondateur->id, $inscription->annulation_validee_par);
        $this->assertNotNull($inscription->annulation_validee_le);

        // La place est libérée (capacité 2, 0 inscrits actifs)
        $this->assertEquals(2, app(ClasseServiceInterface::class)->getPlacesRestantes($this->classe->id));

        // L'élève redevient admis
        $this->assertEquals('admis', $eleve->fresh()->statut);
    }

    public function test_fondateur_peut_rejeter_la_demande_d_annulation(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Mballa',
            'prenom' => 'Sophie',
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => 'Mballa Marc',
            'telephone_tuteur' => '677445566',
            'derniere_classe_frequente' => 'SIL',
            'matricule_permanent' => 'AMB-TEST-MBA-01',
        ]);

        $inscriptionId = app(InscriptionServiceInterface::class)->inscrire($eleve->id, $this->classe->id, $this->annee->id);
        app(InscriptionServiceInterface::class)->demanderAnnulation($inscriptionId, 'Demande sans justificatif');

        // Fondateur rejette
        $this->actingAs($this->fondateur);

        Livewire::test(ViewInscription::class, ['record' => $inscriptionId])
            ->callAction('rejeterAnnulation', [
                'motif' => 'Refusé : Aucun justificatif officiel fourni.',
            ])
            ->assertHasNoActionErrors();

        $inscription = Inscription::findOrFail($inscriptionId);
        $this->assertEquals('active', $inscription->statut);
        $this->assertNull($inscription->motif_annulation);

        // La place reste occupée
        $this->assertEquals(1, app(ClasseServiceInterface::class)->getPlacesRestantes($this->classe->id));
    }
}
