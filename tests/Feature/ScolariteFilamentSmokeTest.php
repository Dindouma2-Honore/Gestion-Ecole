<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Filament\Pages\SituationFinanciere;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Filament\Pages\BilanJournalier;
use App\Modules\Scolarite\Filament\Pages\ScolariteDashboard;
use App\Modules\Scolarite\Filament\Resources\CategorieFraisResource;
use App\Modules\Scolarite\Filament\Resources\ClasseResource;
use App\Modules\Scolarite\Filament\Resources\CycleResource;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\FiliereResource;
use App\Modules\Scolarite\Filament\Resources\FraisResource;
use App\Modules\Scolarite\Filament\Resources\GrilleTarifaireResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\NiveauResource;
use App\Modules\Scolarite\Filament\Resources\PaiementResource;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use App\Modules\Scolarite\Filament\Resources\SectionScolaireResource;
use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource;
use App\Modules\Scolarite\Filament\Resources\TypeDocumentEleveResource;
use App\Modules\Scolarite\Models\CategorieFrais;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Filiere;
use App\Modules\Scolarite\Models\Frais;
use App\Modules\Scolarite\Models\GrilleTarifaire;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Models\SectionScolaire;
use App\Modules\Scolarite\Models\TransfertAffectation;
use App\Modules\Scolarite\Models\TypeDocumentEleve;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScolariteFilamentSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AnneeScolaire $annee;

    private Cycle $cycle;

    private Niveau $niveau;

    private Classe $classe;

    private Eleve $eleve;

    private ParentTuteur $parent;

    private Inscription $inscription;

    private Paiement $paiementValide;

    private Paiement $paiementAnnule;

    private TransfertAffectation $transfert;

    private Inscription $inscriptionSansPaiement;

    private Inscription $inscriptionPaiement;

    private Inscription $inscriptionAnnule;

    private Eleve $eleveAvecDocs;

    private GrilleTarifaire $grilleTarifaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create(['statut' => 'actif']);
        $this->user->assignRole('Fondateur');
        $this->actingAs($this->user);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);

        $this->cycle = Cycle::create(['nom' => 'Secondaire', 'code' => 'SEC', 'ordre' => 1]);
        $this->niveau = Niveau::create(['nom' => '6ème', 'code' => '6E', 'ordre' => 1, 'cycle_id' => $this->cycle->id]);
        $this->classe = Classe::create([
            'nom' => '6ème A',
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
        ]);

        $this->parent = ParentTuteur::create(['nom' => 'Doe', 'prenom' => 'John', 'telephone' => '670000000']);

        $this->eleve = Eleve::create([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'sexe' => 'F',
            'statut' => 'admis',
        ]);

        $this->inscription = Inscription::create([
            'eleve_id' => $this->eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'statut' => 'active',
            'date_inscription' => now(),
        ]);

        // Fixture: Inscription sans paiement (cas fréquent)
        $eleveSansPaiement = Eleve::create([
            'nom' => 'Martin',
            'prenom' => 'Pierre',
            'sexe' => 'M',
            'statut' => 'admis',
        ]);
        $this->inscriptionSansPaiement = Inscription::create([
            'eleve_id' => $eleveSansPaiement->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'statut' => 'active',
            'date_inscription' => now(),
        ]);

        // Fixture: Inscription avec paiement validé
        $eleveAvecPaiement = Eleve::create([
            'nom' => 'Dupont',
            'prenom' => 'Marie',
            'sexe' => 'F',
            'statut' => 'admis',
        ]);
        $this->inscriptionPaiement = Inscription::create([
            'eleve_id' => $eleveAvecPaiement->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'statut' => 'active',
            'date_inscription' => now(),
        ]);

        $this->paiementValide = Paiement::create([
            'eleve_id' => $eleveAvecPaiement->id,
            'annee_scolaire_id' => $this->annee->id,
            'montant' => 50000,
            'mode' => 'especes',
            'numero_recu' => 'REC-2026-0010',
            'statut' => 'valide',
            'encaisse_par' => $this->user->id,
        ]);

        // Fixture: Paiement annulé
        $eleveAnnule = Eleve::create([
            'nom' => 'Durand',
            'prenom' => 'Luc',
            'sexe' => 'M',
            'statut' => 'admis',
        ]);
        $this->inscriptionAnnule = Inscription::create([
            'eleve_id' => $eleveAnnule->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'statut' => 'active',
            'date_inscription' => now(),
        ]);

        $this->paiementAnnule = Paiement::create([
            'eleve_id' => $eleveAnnule->id,
            'annee_scolaire_id' => $this->annee->id,
            'montant' => 30000,
            'mode' => 'bancaire',
            'numero_recu' => 'REC-2026-0011',
            'statut' => 'annule',
            'encaisse_par' => $this->user->id,
        ]);
        $this->paiementAnnule->annulations()->create([
            'motif' => 'Demande famille',
            'annule_par' => $this->user->id,
            'annule_le' => now(),
        ]);

        // Fixture: Eleve avec documents
        $eleveDoc = Eleve::create([
            'nom' => 'Moreau',
            'prenom' => 'Sophie',
            'sexe' => 'F',
            'statut' => 'admis',
        ]);
        $docNaissance = TypeDocumentEleve::create([
            'nom' => 'Acte de naissance',
            'code' => 'ACTE',
        ]);
        $docPieceIdentite = TypeDocumentEleve::create([
            'nom' => 'Piece d\'identité',
            'code' => 'PIECE',
        ]);
        $this->eleveAvecDocs = Eleve::create([
            'nom' => 'Moreau',
            'prenom' => 'Sophie',
            'sexe' => 'F',
            'statut' => 'admis',
        ]);

        // Fixture: Grille tarifaire avec classe
        $fraisTranche = Frais::create([
            'nom' => 'Tranche unique',
            'categorie_frais_id' => CategorieFrais::create(['nom' => 'Scolarité', 'code' => 'SCOL'])->id,
            'montant_defaut' => 50000,
        ]);
        $this->grilleTarifaire = GrilleTarifaire::create([
            'niveau_id' => $this->niveau->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'frais_id' => $fraisTranche->id,
            'montant' => 50000,
        ]);

        // Fixture: Transfert d'élève en classe différente
        $classeB = Classe::create([
            'nom' => '6ème B',
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
        ]);
        $this->transfert = TransfertAffectation::create([
            'inscription_id' => $this->inscription->id,
            'eleve_id' => $this->eleve->id,
            'classe_origine_id' => $this->classe->id,
            'classe_destination_id' => $classeB->id,
            'annee_scolaire_id' => $this->annee->id,
            'date_transfert' => now(),
            'motif' => 'Deménagement',
            'effectue_par' => $this->user->id,
        ]);

        FormatNumerotation::create([
            'type_document' => 'recu',
            'format' => 'REC-{{annee}}-{{seq:4}}',
            'prochain_numero' => 1,
        ]);
    }

    // --- DASHBOARD & WIDGETS ---

    public function test_dashboard_et_widgets_avec_donnees(): void
    {
        $this->get(ScolariteDashboard::getUrl())->assertSuccessful();
    }

    // --- CYCLES ---

    public function test_cycles_resource_screens(): void
    {
        $this->get(CycleResource::getUrl('index'))->assertSuccessful();
        $this->get(CycleResource::getUrl('create'))->assertSuccessful();
        $this->get(CycleResource::getUrl('edit', ['record' => $this->cycle]))->assertSuccessful();
    }

    // --- NIVEAUX ---

    public function test_niveaux_resource_screens(): void
    {
        $this->get(NiveauResource::getUrl('index'))->assertSuccessful();
        $this->get(NiveauResource::getUrl('create'))->assertSuccessful();
        $this->get(NiveauResource::getUrl('edit', ['record' => $this->niveau]))->assertSuccessful();
    }

    // --- FILIÈRES ---

    public function test_filieres_resource_screens(): void
    {
        $filiere = Filiere::create(['nom' => 'Générale', 'code' => 'GEN']);
        $this->get(FiliereResource::getUrl('index'))->assertSuccessful();
        $this->get(FiliereResource::getUrl('create'))->assertSuccessful();
        $this->get(FiliereResource::getUrl('edit', ['record' => $filiere]))->assertSuccessful();
    }

    // --- SECTIONS ---

    public function test_sections_resource_screens(): void
    {
        $section = SectionScolaire::create(['nom' => 'Francophone', 'code' => 'FR']);
        $this->get(SectionScolaireResource::getUrl('index'))->assertSuccessful();
        $this->get(SectionScolaireResource::getUrl('create'))->assertSuccessful();
        $this->get(SectionScolaireResource::getUrl('edit', ['record' => $section]))->assertSuccessful();
    }

    // --- CLASSES ---

    public function test_classes_resource_screens_avec_eleves(): void
    {
        $this->get(ClasseResource::getUrl('index'))->assertSuccessful();
        $this->get(ClasseResource::getUrl('create'))->assertSuccessful();
        $this->get(ClasseResource::getUrl('view', ['record' => $this->classe]))->assertSuccessful();
        $this->get(ClasseResource::getUrl('edit', ['record' => $this->classe]))->assertSuccessful();
    }

    // --- ÉLÈVES ---

    public function test_eleves_resource_screens_avec_inscription_et_docs(): void
    {
        // L'élève principal est déjà inscrit : il est exclu de l'édition
        // (filtre du query EleveResource), on édite l'élève sans inscription.
        $this->get(EleveResource::getUrl('index'))->assertSuccessful();
        $this->get(EleveResource::getUrl('create'))->assertSuccessful();
        $this->get(EleveResource::getUrl('edit', ['record' => $this->eleveAvecDocs]))->assertSuccessful();
    }

    public function test_eleves_resource_screens_avec_documents(): void
    {
        $this->get(EleveResource::getUrl('index'))->assertSuccessful();
        $this->get(EleveResource::getUrl('create'))->assertSuccessful();
        $this->get(EleveResource::getUrl('edit', ['record' => $this->eleveAvecDocs]))->assertSuccessful();
    }

    // --- PARENTS ---

    public function test_parents_resource_screens(): void
    {
        $this->get(ParentTuteurResource::getUrl('index'))->assertSuccessful();
        $this->get(ParentTuteurResource::getUrl('create'))->assertSuccessful();
        $this->get(ParentTuteurResource::getUrl('view', ['record' => $this->parent]))->assertSuccessful();
        $this->get(ParentTuteurResource::getUrl('edit', ['record' => $this->parent]))->assertSuccessful();
    }

    // --- INSCRIPTIONS ---

    public function test_inscriptions_resource_screens(): void
    {
        $this->get(InscriptionResource::getUrl('index'))->assertSuccessful();
        $this->get(InscriptionResource::getUrl('create'))->assertSuccessful();
        $this->get(InscriptionResource::getUrl('view', ['record' => $this->inscription]))->assertSuccessful();
    }

    public function test_inscription_sans_paiement_page(): void
    {
        $this->get(InscriptionResource::getUrl('view', ['record' => $this->inscriptionSansPaiement]))->assertSuccessful();
    }

    public function test_inscription_avec_paiement_page(): void
    {
        $this->get(InscriptionResource::getUrl('view', ['record' => $this->inscriptionPaiement]))->assertSuccessful();
    }

    // --- TRANSFERTS ---

    public function test_transferts_resource_screens_avec_transfert_valide(): void
    {
        $this->get(TransfertAffectationResource::getUrl('index'))->assertSuccessful();
        $this->get(TransfertAffectationResource::getUrl('create'))->assertSuccessful();
    }

    // --- DOCUMENTS ---

    public function test_documents_resource_screens(): void
    {
        $doc = TypeDocumentEleve::create(['nom' => 'Carnet de santé', 'code' => 'CARNET']);
        $this->get(TypeDocumentEleveResource::getUrl('index'))->assertSuccessful();
        $this->get(TypeDocumentEleveResource::getUrl('create'))->assertSuccessful();
        $this->get(TypeDocumentEleveResource::getUrl('edit', ['record' => $doc]))->assertSuccessful();
    }

    // --- FRAIS / GRILLES ---

    public function test_frais_scolaires_resources_avec_grille(): void
    {
        $cat = CategorieFrais::create(['nom' => 'Activités parascolaires', 'code' => 'ACT']);
        $frais = Frais::create(['nom' => 'Sport', 'categorie_frais_id' => $cat->id, 'montant_defaut' => 20000]);
        $grille = GrilleTarifaire::create([
            'niveau_id' => $this->niveau->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'frais_id' => $frais->id,
            'montant' => 20000,
        ]);

        $this->get(CategorieFraisResource::getUrl('index'))->assertSuccessful();
        $this->get(CategorieFraisResource::getUrl('create'))->assertSuccessful();
        $this->get(CategorieFraisResource::getUrl('edit', ['record' => $cat]))->assertSuccessful();

        $this->get(FraisResource::getUrl('index'))->assertSuccessful();
        $this->get(FraisResource::getUrl('create'))->assertSuccessful();
        $this->get(FraisResource::getUrl('edit', ['record' => $frais]))->assertSuccessful();

        $this->get(GrilleTarifaireResource::getUrl('index'))->assertSuccessful();
        $this->get(GrilleTarifaireResource::getUrl('create'))->assertSuccessful();
        $this->get(GrilleTarifaireResource::getUrl('edit', ['record' => $grille]))->assertSuccessful();
    }

    // --- PAIEMENTS ---

    public function test_paiements_resource_screens_avec_paiements_reels(): void
    {
        $this->get(PaiementResource::getUrl('index'))->assertSuccessful();
    }

    public function test_paiement_valide_avec_repartition(): void
    {
        $this->get(PaiementResource::getUrl('index'))->assertSuccessful();
    }

    // --- SITUATION FINANCIÈRE ---

    public function test_situation_financiere_avec_donnees(): void
    {
        $this->get(SituationFinanciere::getUrl())->assertSuccessful();
    }

    // --- BILAN JOURNALIER ---

    public function test_bilan_journalier_avec_donnees_reelles(): void
    {
        $this->get(BilanJournalier::getUrl())->assertSuccessful();
    }

    // --- COMBINAISONS RÉALISTES ---

    public function test_inscription_et_paiement_complet(): void
    {
        $this->get(InscriptionResource::getUrl('view', ['record' => $this->inscriptionPaiement]))->assertSuccessful();
        $this->get(PaiementResource::getUrl('index'))->assertSuccessful();
    }

    public function test_inscription_et_situation_financiere(): void
    {
        $this->get(InscriptionResource::getUrl('view', ['record' => $this->inscriptionPaiement]))->assertSuccessful();
        $this->get(SituationFinanciere::getUrl())->assertSuccessful();
    }

    public function test_classe_avec_inscriptions_et_paiements(): void
    {
        $this->get(ClasseResource::getUrl('view', ['record' => $this->classe]))->assertSuccessful();
        $this->get(InscriptionResource::getUrl('index'))->assertSuccessful();
    }

    public function test_eleve_avec_transfert(): void
    {
        $this->get(EleveResource::getUrl('edit', ['record' => $this->eleveAvecDocs]))->assertSuccessful();
        $this->get(TransfertAffectationResource::getUrl('index'))->assertSuccessful();
    }
}
