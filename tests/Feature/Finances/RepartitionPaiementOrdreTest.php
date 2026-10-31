<?php

declare(strict_types=1);

namespace Tests\Feature\Finances;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Contracts\RepartitionPaiementServiceContract;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\GroupeFrais;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\PaiementTrancheAllocation;
use App\Modules\Finances\Models\TrancheFraisClasse;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepartitionPaiementOrdreTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Niveau $niveau;

    private Classe $classe;

    private User $comptable;

    private CatalogueFraisDivers $fraisTenue;

    private CatalogueFraisDivers $fraisAssurance;

    private ConfigurationFraisClasse $configClasse;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');

        $this->annee = AnneeScolaire::query()->create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $this->niveau = Niveau::query()->create([
            'nom' => '6ème',
            'code' => '6E',
            'ordre' => 7,
        ]);

        $this->classe = Classe::query()->create([
            'nom' => '6ème A',
            'code' => '6A',
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'active',
            'capacite_max' => 50,
        ]);

        $groupe = GroupeFrais::query()->firstOrCreate(
            ['code' => 'scolarite'],
            ['nom' => 'Frais de scolarité', 'ordre' => 1]
        );

        TypeFraisRecurrent::query()->create([
            'nom' => "Frais d'inscription",
            'nature' => 'inscription',
            'groupe_frais_id' => $groupe->id,
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'montant' => 15000,
            'actif' => true,
        ]);

        FormatNumerotation::query()->create([
            'type_document' => 'recu',
            'format' => 'REC-{{annee}}-{{seq:4}}',
            'prochain_numero' => 1,
        ]);

        $this->fraisTenue = CatalogueFraisDivers::query()->create([
            'nom' => 'Tenue scolaire',
            'categorie' => 'tenue',
            'montant_defaut' => 25000,
            'periodicite' => 'unique',
            'groupe_frais_id' => $groupe->id,
            'actif' => true,
        ]);

        $this->fraisAssurance = CatalogueFraisDivers::query()->create([
            'nom' => 'Assurance scolaire',
            'categorie' => 'autre',
            'montant_defaut' => 5000,
            'periodicite' => 'unique',
            'groupe_frais_id' => $groupe->id,
            'actif' => true,
        ]);

        // Configuration de la classe avec 3 tranches (Total = 100 000 FCFA)
        $this->configClasse = ConfigurationFraisClasse::query()->create([
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'montant_total' => 100000,
            'actif' => true,
        ]);

        TrancheFraisClasse::query()->create([
            'configuration_frais_classe_id' => $this->configClasse->id,
            'libelle' => 'Tranche 1',
            'montant' => 50000,
            'ordre' => 1,
            'date_echeance' => '2026-10-31',
        ]);
        TrancheFraisClasse::query()->create([
            'configuration_frais_classe_id' => $this->configClasse->id,
            'libelle' => 'Tranche 2',
            'montant' => 30000,
            'ordre' => 2,
            'date_echeance' => '2027-01-31',
        ]);
        TrancheFraisClasse::query()->create([
            'configuration_frais_classe_id' => $this->configClasse->id,
            'libelle' => 'Tranche 3',
            'montant' => 20000,
            'ordre' => 3,
            'date_echeance' => '2027-04-30',
        ]);

        Role::firstOrCreate(['name' => 'Comptable', 'guard_name' => 'web']);
        $this->comptable = User::factory()->create(['statut' => 'actif']);
        $this->comptable->assignRole('Comptable');
        $this->actingAs($this->comptable);

        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_calculer_montant_minimum_sans_et_avec_frais_divers(): void
    {
        $port = app(InscriptionFacturationPort::class);

        // Sans frais divers
        $this->assertSame(15000.0, $port->calculerMontantMinimum([]));

        // Avec Tenue scolaire (25 000 FCFA) -> 40 000 FCFA
        $this->assertSame(40000.0, $port->calculerMontantMinimum(['divers-'.$this->fraisTenue->id]));

        // Avec Tenue scolaire (25 000 FCFA) + Assurance (5 000 FCFA) -> 45 000 FCFA
        $this->assertSame(45000.0, $port->calculerMontantMinimum([
            'divers-'.$this->fraisTenue->id,
            'divers-'.$this->fraisAssurance->id,
        ]));
    }

    public function test_simuler_repartition_montant_insuffisant_avec_frais_divers(): void
    {
        $port = app(InscriptionFacturationPort::class);

        // Parent sélectionne Tenue scolaire (25 000 FCFA), minimum requis = 40 000 FCFA
        // Montant saisi = 35 000 FCFA (insuffisant de 5 000 FCFA)
        $sim = $port->simulerRepartition(
            montantVerse: 35000,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            classeId: $this->classe->id,
            anneeId: $this->annee->id,
        );

        $this->assertFalse($sim['est_suffisant']);
        $this->assertSame(40000.0, $sim['montant_minimum']);
        $this->assertSame(5000.0, $sim['manquant']);
        $this->assertTrue($sim['has_divers']);

        // Inscription couverte (15 000 FCFA)
        $this->assertSame(15000.0, $sim['inscription']['montant_affecte']);
        $this->assertSame('couvert', $sim['inscription']['statut']);

        // Frais divers partiel (20 000 FCFA sur 25 000 FCFA)
        $this->assertSame(20000.0, $sim['frais_divers']['total_affecte']);
        $this->assertSame('partiel', $sim['frais_divers']['statut']);

        // RÈGLE ABSOLUE : Scolarité = 0 FCFA tant que inscription + divers non couverts à 100% !
        $this->assertSame(0.0, $sim['scolarite']['montant_affecte']);
        $this->assertSame('non_paye', $sim['scolarite']['statut']);
        foreach ($sim['scolarite']['tranches'] as $tranche) {
            $this->assertSame(0.0, $tranche['montant_affecte']);
            $this->assertSame('non_paye', $tranche['statut']);
        }
    }

    public function test_simuler_repartition_montant_exact_minimum(): void
    {
        $port = app(InscriptionFacturationPort::class);

        // Montant saisi = 40 000 FCFA
        $sim = $port->simulerRepartition(
            montantVerse: 40000,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            classeId: $this->classe->id,
            anneeId: $this->annee->id,
        );

        $this->assertTrue($sim['est_suffisant']);
        $this->assertSame(40000.0, $sim['montant_minimum']);
        $this->assertSame(0.0, $sim['manquant']);

        // Inscription = 15 000 FCFA (couvert)
        $this->assertSame(15000.0, $sim['inscription']['montant_affecte']);
        $this->assertSame('couvert', $sim['inscription']['statut']);

        // Divers = 25 000 FCFA (couvert)
        $this->assertSame(25000.0, $sim['frais_divers']['total_affecte']);
        $this->assertSame('couvert', $sim['frais_divers']['statut']);

        // Scolarité = 0 FCFA affecté
        $this->assertSame(0.0, $sim['scolarite']['montant_affecte']);
    }

    public function test_simuler_repartition_surplus_ventile_chronologiquement_sur_les_tranches(): void
    {
        $port = app(InscriptionFacturationPort::class);

        // Montant versé = 100 000 FCFA
        // Inscription: 15 000 FCFA
        // Tenue: 25 000 FCFA
        // Surplus pour scolarité: 60 000 FCFA
        // Tranche 1 (50 000 FCFA) -> 50 000 FCFA (couvert)
        // Tranche 2 (30 000 FCFA) -> 10 000 FCFA (partiel)
        // Tranche 3 (20 000 FCFA) -> 0 FCFA (non affecté)
        $sim = $port->simulerRepartition(
            montantVerse: 100000,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            classeId: $this->classe->id,
            anneeId: $this->annee->id,
        );

        $this->assertTrue($sim['est_suffisant']);
        $this->assertSame(15000.0, $sim['inscription']['montant_affecte']);
        $this->assertSame('couvert', $sim['inscription']['statut']);

        $this->assertSame(25000.0, $sim['frais_divers']['total_affecte']);
        $this->assertSame('couvert', $sim['frais_divers']['statut']);

        $this->assertSame(60000.0, $sim['scolarite']['montant_affecte']);
        $this->assertSame('partiel', $sim['scolarite']['statut']);

        $tranches = $sim['scolarite']['tranches'];
        $this->assertCount(3, $tranches);

        $this->assertSame('Tranche 1', $tranches[0]['nom']);
        $this->assertSame(50000.0, $tranches[0]['montant_affecte']);
        $this->assertSame('couvert', $tranches[0]['statut']);

        $this->assertSame('Tranche 2', $tranches[1]['nom']);
        $this->assertSame(10000.0, $tranches[1]['montant_affecte']);
        $this->assertSame('partiel', $tranches[1]['statut']);

        $this->assertSame('Tranche 3', $tranches[2]['nom']);
        $this->assertSame(0.0, $tranches[2]['montant_affecte']);
        $this->assertSame('non_paye', $tranches[2]['statut']);

        $this->assertSame(100000.0, $sim['total_affecte']);
    }

    public function test_creer_facture_provisoire_bloque_si_versement_inferieur_au_minimum_obligatoire(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Kuate', 'prenom' => 'Alain', 'sexe' => 'M', 'statut' => 'admis',
            'matricule_permanent' => 'AMB-TEST-KUAT-01',
        ]);
        $parent = ParentTuteur::create(['nom' => 'Kuate', 'prenom' => 'Jean', 'telephone' => '677112233']);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id, 'date_inscription' => now(), 'statut' => 'en_attente_versement',
        ]);

        $service = app(FacturePreinscriptionServiceContract::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Transaction impossible. Le montant minimum à payer est de 40 000 FCFA, comprenant l'inscription et les frais divers sélectionnés.");

        // Versement de 30 000 au lieu de 40 000
        $service->creerFactureProvisoire(
            inscriptionId: $inscription->id,
            eleveId: $eleve->id,
            niveauId: $this->niveau->id,
            classeId: $this->classe->id,
            anneeScolaireId: $this->annee->id,
            parentId: $parent->id,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            montantVerse: 30000,
            identite: ['eleve' => 'Kuate Alain', 'parent' => 'Kuate Jean'],
        );
    }

    public function test_confirmer_versement_bloque_si_montant_confirme_inferieur_au_minimum(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Kamga', 'prenom' => 'Brice', 'sexe' => 'M', 'statut' => 'admis',
            'matricule_permanent' => 'AMB-TEST-KAMG-01',
        ]);
        $parent = ParentTuteur::create(['nom' => 'Kamga', 'prenom' => 'Paul', 'telephone' => '677112244']);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id, 'date_inscription' => now(), 'statut' => 'en_attente_versement',
        ]);

        $service = app(FacturePreinscriptionServiceContract::class);

        // Crée une facture avec 40 000 FCFA
        $facture = $service->creerFactureProvisoire(
            inscriptionId: $inscription->id,
            eleveId: $eleve->id,
            niveauId: $this->niveau->id,
            classeId: $this->classe->id,
            anneeScolaireId: $this->annee->id,
            parentId: $parent->id,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            montantVerse: 40000,
            identite: ['eleve' => 'Kamga Brice', 'parent' => 'Kamga Paul'],
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Transaction impossible. Le montant minimum à payer est de 40 000 FCFA, comprenant l'inscription et les frais divers sélectionnés.");

        // Le comptable tente de valider seulement 25 000 FCFA
        $service->confirmerVersement(
            factureId: $facture->id,
            mode: 'especes',
            referenceTransaction: null,
            montant: 25000,
        );
    }

    public function test_repartition_paiement_service_ordre_strict_et_tranches_chronologiques(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Ewondo', 'prenom' => 'Marc', 'sexe' => 'M', 'statut' => 'admis',
            'matricule_permanent' => 'AMB-TEST-EWON-01',
        ]);
        $parent = ParentTuteur::create(['nom' => 'Ewondo', 'prenom' => 'Joseph', 'telephone' => '677112255']);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id, 'date_inscription' => now(), 'statut' => 'en_attente_versement',
        ]);

        $factureService = app(FacturePreinscriptionServiceContract::class);
        $repartitionService = app(RepartitionPaiementServiceContract::class);

        // Facture avec tenue (25 000) et versement prévu 100 000 FCFA
        $facture = $factureService->creerFactureProvisoire(
            inscriptionId: $inscription->id,
            eleveId: $eleve->id,
            niveauId: $this->niveau->id,
            classeId: $this->classe->id,
            anneeScolaireId: $this->annee->id,
            parentId: $parent->id,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            montantVerse: 100000,
            identite: ['eleve' => 'Ewondo Marc', 'parent' => 'Ewondo Joseph'],
        );

        // 1. Tenter un versement inférieur au minimum requis (35 000 FCFA au lieu de 40 000 FCFA) -> Bloqué
        try {
            $repartitionService->repartir($inscription->id, 35000, 'especes');
            $this->fail('Le paiement inférieur au minimum avec frais divers aurait dû être bloqué.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString("Transaction impossible. Le montant minimum à payer est de 40 000 FCFA, comprenant l'inscription et les frais divers sélectionnés.", $e->getMessage());
        }

        // 2. Exécuter le versement complet de 100 000 FCFA
        $resultat = $repartitionService->repartir($inscription->id, 100000, 'especes');

        $this->assertSame(100000.0, $resultat['montant']);
        $refVersement = $resultat['versement_reference'];

        // Vérification des 3 paiements générés dans la table paiements
        $paiements = Paiement::query()->where('versement_reference', $refVersement)->get();
        $this->assertCount(3, $paiements);

        // Paiement 1 : Inscription = 15 000 FCFA (100% payé)
        $paiementInscription = $paiements->firstWhere('rubrique', 'inscription');
        $this->assertNotNull($paiementInscription);
        $this->assertSame(15000.0, (float) $paiementInscription->montant);

        // Paiement 2 : Frais divers = 25 000 FCFA (100% payé)
        $paiementDivers = $paiements->first(fn ($p) => str_starts_with((string) $p->rubrique, 'divers:'));
        $this->assertNotNull($paiementDivers);
        $this->assertSame(25000.0, (float) $paiementDivers->montant);
        $this->assertNotNull($paiementDivers->frais_divers_eleve_id);
        $this->assertEquals('Tenue scolaire', $paiementDivers->fraisDivers->poste->nom);

        // Paiement 3 : Scolarité = 60 000 FCFA
        $paiementScolarite = $paiements->firstWhere('rubrique', 'tranches_classe');
        $this->assertNotNull($paiementScolarite);
        $this->assertSame(60000.0, (float) $paiementScolarite->montant);

        // Vérification de la ventilation chronologique sur les tranches
        $allocations = PaiementTrancheAllocation::query()
            ->where('paiement_id', $paiementScolarite->id)
            ->with('tranche')
            ->get();

        $allocTranche1 = $allocations->first(fn ($a) => $a->tranche->libelle === 'Tranche 1');
        $this->assertNotNull($allocTranche1);
        $this->assertSame(50000.0, (float) $allocTranche1->montant);

        $allocTranche2 = $allocations->first(fn ($a) => $a->tranche->libelle === 'Tranche 2');
        $this->assertNotNull($allocTranche2);
        $this->assertSame(10000.0, (float) $allocTranche2->montant);

        $allocTranche3 = $allocations->first(fn ($a) => $a->tranche->libelle === 'Tranche 3');
        $this->assertNull($allocTranche3); // 0 FCFA alloué à la tranche 3
    }

    public function test_simuler_repartition_detecte_trop_percu_au_dela_du_total_annuel(): void
    {
        $port = app(InscriptionFacturationPort::class);

        // Total annuel : Inscription (15 000) + Tenue (25 000) + Scolarité 3 tranches (100 000) = 140 000 FCFA
        // Parent donne 150 000 FCFA (surplus de 10 000 FCFA)
        $sim = $port->simulerRepartition(
            montantVerse: 150000,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            classeId: $this->classe->id,
            anneeId: $this->annee->id,
        );

        $this->assertTrue($sim['est_suffisant']);
        $this->assertTrue($sim['depassement_total']);
        $this->assertSame(140000.0, $sim['total_annuel']);
        $this->assertSame(10000.0, $sim['trop_percu']);
        $this->assertSame(15000.0, $sim['inscription']['montant_affecte']);
        $this->assertSame('couvert', $sim['inscription']['statut']);
        $this->assertSame(25000.0, $sim['frais_divers']['total_affecte']);
        $this->assertSame('couvert', $sim['frais_divers']['statut']);
        $this->assertSame(100000.0, $sim['scolarite']['montant_affecte']);
        $this->assertSame('couvert', $sim['scolarite']['statut']);

        foreach ($sim['scolarite']['tranches'] as $tranche) {
            $this->assertSame('couvert', $tranche['statut']);
            $this->assertSame($tranche['montant_total'], $tranche['montant_affecte']);
        }
    }

    public function test_creer_facture_provisoire_bloque_si_versement_depasse_le_total_annuel(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Mbida', 'prenom' => 'Serge', 'sexe' => 'M', 'statut' => 'admis',
            'matricule_permanent' => 'AMB-TEST-MBID-01',
        ]);
        $parent = ParentTuteur::create(['nom' => 'Mbida', 'prenom' => 'Pierre', 'telephone' => '677112266']);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id, 'date_inscription' => now(), 'statut' => 'en_attente_versement',
        ]);

        $service = app(FacturePreinscriptionServiceContract::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Transaction impossible. Le montant versé de 150 000 FCFA dépasse le montant total annuel dû (140 000 FCFA). Monnaie à restituer au parent : 10 000 FCFA.');

        // Versement de 150 000 FCFA au lieu du total max de 140 000 FCFA
        $service->creerFactureProvisoire(
            inscriptionId: $inscription->id,
            eleveId: $eleve->id,
            niveauId: $this->niveau->id,
            classeId: $this->classe->id,
            anneeScolaireId: $this->annee->id,
            parentId: $parent->id,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            montantVerse: 150000,
            identite: ['eleve' => 'Mbida Serge', 'parent' => 'Mbida Pierre'],
        );
    }

    public function test_confirmer_versement_bloque_si_montant_depasse_le_total_facture(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Bella', 'prenom' => 'Carine', 'sexe' => 'F', 'statut' => 'admis',
            'matricule_permanent' => 'AMB-TEST-BELL-01',
        ]);
        $parent = ParentTuteur::create(['nom' => 'Bella', 'prenom' => 'Claire', 'telephone' => '677112277']);
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id, 'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id, 'date_inscription' => now(), 'statut' => 'en_attente_versement',
        ]);

        $service = app(FacturePreinscriptionServiceContract::class);

        $facture = $service->creerFactureProvisoire(
            inscriptionId: $inscription->id,
            eleveId: $eleve->id,
            niveauId: $this->niveau->id,
            classeId: $this->classe->id,
            anneeScolaireId: $this->annee->id,
            parentId: $parent->id,
            fraisOptionnels: ['divers-'.$this->fraisTenue->id],
            montantVerse: 140000,
            identite: ['eleve' => 'Bella Carine', 'parent' => 'Bella Claire'],
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Transaction impossible. Le montant reçu de 145 000 FCFA dépasse le montant total annuel dû (140 000 FCFA). Monnaie à restituer au parent : 5 000 FCFA.');

        // Le comptable saisit par mégarde 145 000 FCFA
        $service->confirmerVersement(
            factureId: $facture->id,
            mode: 'especes',
            referenceTransaction: null,
            montant: 145000,
        );
    }
}
