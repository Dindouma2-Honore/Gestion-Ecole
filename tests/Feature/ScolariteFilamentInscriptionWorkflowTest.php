<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages\ListEleves;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ViewInscription;
use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource;
use App\Modules\Scolarite\Filament\Resources\TransfertAffectationResource\Pages\CreateTransfertAffectation;
use App\Modules\Scolarite\Filament\Resources\TypeDocumentEleveResource\Pages\ListTypesDocumentsEleve;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\DocumentEleve;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Models\TypeDocumentEleve;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ScolariteFilamentInscriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AnneeScolaire $annee;

    private Niveau $niveau;

    private Classe $classe;

    private Classe $classeB;

    private Enseignant $enseignant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['statut' => 'actif']);
        $this->admin->assignRole('Fondateur');
        $this->actingAs($this->admin);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);

        $cycle = Cycle::create(['nom' => 'Secondaire', 'code' => 'SEC', 'ordre' => 2]);
        $this->niveau = Niveau::create(['nom' => '6ème', 'code' => '6E', 'ordre' => 1, 'cycle_id' => $cycle->id]);

        $employe = Employe::create([
            'matricule' => 'ENS-INS',
            'nom' => 'Prof',
            'prenom' => 'Alain',
            'poste' => 'Enseignant',
            'date_embauche' => today(),
            'statut' => 'actif',
        ]);
        $this->enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'specialite' => 'Maths',
            'statut_contractuel' => 'titulaire',
            'charge_horaire_hebdo' => 18,
        ]);

        $this->classe = Classe::create([
            'nom' => '6ème A',
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
            'professeur_principal_id' => $this->enseignant->id,
        ]);
        FormatNumerotation::create([
            'type_document' => 'recu',
            'format' => 'REC-{{annee}}-{{seq:4}}',
            'prochain_numero' => 1,
        ]);

        $this->classeB = Classe::create([
            'nom' => '6ème B',
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
            'professeur_principal_id' => $this->enseignant->id,
        ]);
    }

    private function eleveAdmissible(string $nom, string $prenom): Eleve
    {
        $eleve = Eleve::create([
            'nom' => $nom,
            'prenom' => $prenom,
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => "{$nom} Parent",
            'telephone_tuteur' => '670'.random_int(100000, 999999),
        ]);
        $eleve->synchroniserParentTuteur();

        return $eleve;
    }

    public function test_nouvelle_inscription_modale_cree_inscription_et_facture_provisoire(): void
    {
        $eleve = $this->eleveAdmissible('Owona', 'Sandrine');

        Livewire::test(ListInscriptions::class)
            ->assertSuccessful()
            ->assertActionExists('create');

        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => false,
            ]);

        $inscription = Inscription::withoutGlobalScopes()
            ->where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $this->annee->id)
            ->firstOrFail();

        $this->assertSame('en_attente_versement', $inscription->statut);
        $this->assertSame('inscription', $inscription->type);
        $this->assertSame($this->classe->id, (int) $inscription->classe_id);

        $facture = \App\Modules\Finances\Models\FacturePreinscription::where('inscription_id', $inscription->id)->firstOrFail();
        $this->assertSame('en_attente_versement', $facture->statut);
        $this->assertSame(15000.0, (float) $facture->montant_total);

        $this->get(InscriptionResource::getUrl('index'))->assertSuccessful();
        $this->get(ViewInscription::getUrl(['record' => $inscription]))->assertSuccessful();
    }

    public function test_nouvelle_inscription_avec_validation_immediate_du_paiement(): void
    {
        $eleve = $this->eleveAdmissible('Fotso', 'Déborah');

        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ]);

        $inscription = Inscription::withoutGlobalScopes()
            ->where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $this->annee->id)
            ->firstOrFail();

        $this->assertSame('validee', $inscription->statut);
        $this->assertSame('inscrit', $eleve->fresh()->statut);

        $facture = \App\Modules\Finances\Models\FacturePreinscription::where('inscription_id', $inscription->id)->firstOrFail();
        $this->assertSame('payee', $facture->statut);

        $this->assertDatabaseHas('paiements', [
            'inscription_id' => $inscription->id,
            'statut' => 'valide',
        ]);
    }

    public function test_reinscription_dun_eleve_deja_inscrit_l_annee_precedente(): void
    {
        // Année précédente : le dossier historique est écrit tant que l'année
        // n'est pas encore clôturée, puis elle est clôturée (réinscription
        // d'un ancien élève inscrit l'an dernier).
        $anneePrecedente = AnneeScolaire::create([
            'libelle' => '2025-2026',
            'date_debut' => '2025-09-01',
            'date_fin' => '2026-06-30',
            'statut' => 'brouillon',
        ]);
        $classePrecedente = Classe::create([
            'nom' => '5ème A',
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $anneePrecedente->id,
            'capacite_max' => 30,
            'statut' => 'active',
            'professeur_principal_id' => $this->enseignant->id,
        ]);

        $eleve = $this->eleveAdmissible('Biya', 'Roland');
        Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $classePrecedente->id,
            'annee_scolaire_id' => $anneePrecedente->id,
            'type' => 'inscription',
            'date_inscription' => '2025-09-10',
            'statut' => 'validee',
        ]);
        $anneePrecedente->update(['statut' => 'cloturee']);

        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => false,
            ]);

        $reinscription = Inscription::withoutGlobalScopes()
            ->where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $this->annee->id)
            ->firstOrFail();

        $this->assertSame('reinscription', $reinscription->type);
        $this->assertSame('en_attente_versement', $reinscription->statut);
    }

    public function test_double_inscription_de_leleve_est_rejetee(): void
    {
        $eleve = $this->eleveAdmissible('Mbarga', 'Céline');

        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => false,
            ]);

        $this->assertSame(1, Inscription::withoutGlobalScopes()->where('eleve_id', $eleve->id)->count());

        $component = Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classeB->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => false,
            ]);
        $component->assertHasActionErrors(['eleve_id']);

        $this->assertSame(1, Inscription::withoutGlobalScopes()->where('eleve_id', $eleve->id)->count());
    }

    public function test_transfert_de_classe_depuis_la_page_filament(): void
    {
        $eleve = $this->eleveAdmissible('Kamga', 'Aude');
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'date_inscription' => now(),
            'statut' => 'validee',
        ]);

        $this->get(TransfertAffectationResource::getUrl('create'))->assertSuccessful();

        Livewire::test(CreateTransfertAffectation::class)
            ->fillForm([
                'inscription_id' => $inscription->id,
                'classe_destination_id' => $this->classeB->id,
                'date_transfert' => today()->toDateString(),
                'motif' => 'Déménagement familial',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('transferts_affectations', [
            'inscription_id' => $inscription->id,
            'classe_origine_id' => $this->classe->id,
            'classe_destination_id' => $this->classeB->id,
            'motif' => 'Déménagement familial',
        ]);

        $inscriptionFresh = $inscription->fresh();
        $this->assertSame($this->classeB->id, (int) $inscriptionFresh->classe_id);
        $this->assertSame('transfert', $inscriptionFresh->type);
    }

    public function test_transfert_vers_la_meme_classe_est_rejete(): void
    {
        $eleve = $this->eleveAdmissible('Nana', 'Grâce');
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'date_inscription' => now(),
            'statut' => 'validee',
        ]);

        Livewire::test(CreateTransfertAffectation::class)
            ->fillForm([
                'inscription_id' => $inscription->id,
                'classe_destination_id' => $this->classe->id,
                'date_transfert' => today()->toDateString(),
                'motif' => 'Erreur de saisie',
            ])
            ->call('create')
            ->assertHasFormErrors(['classe_destination_id']);

        $this->assertSame($this->classe->id, (int) $inscription->fresh()->classe_id);
        $this->assertSame(0, \App\Modules\Scolarite\Models\TransfertAffectation::count());
    }

    public function test_transfert_vers_classe_d_une_autre_annee_est_rejete(): void
    {
        $eleve = $this->eleveAdmissible('Talla', 'Prisca');
        $inscription = Inscription::create([
            'eleve_id' => $eleve->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'inscription',
            'date_inscription' => now(),
            'statut' => 'validee',
        ]);

        $autreAnnee = AnneeScolaire::create([
            'libelle' => '2027-2028',
            'date_debut' => '2027-09-01',
            'date_fin' => '2028-06-30',
            'statut' => 'brouillon',
        ]);
        $classeAutreAnnee = Classe::create([
            'nom' => '6ème A',
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $autreAnnee->id,
            'capacite_max' => 30,
            'statut' => 'active',
            'professeur_principal_id' => $this->enseignant->id,
        ]);

        Livewire::test(CreateTransfertAffectation::class)
            ->fillForm([
                'inscription_id' => $inscription->id,
                'classe_destination_id' => $classeAutreAnnee->id,
                'date_transfert' => today()->toDateString(),
                'motif' => 'Changement d’année',
            ])
            ->call('create')
            ->assertHasFormErrors(['classe_destination_id']);

        $this->assertSame(0, \App\Modules\Scolarite\Models\TransfertAffectation::count());
    }

    public function test_ajout_d_un_document_d_admission_depuis_la_modale_eleve(): void
    {
        Storage::fake('public');

        Livewire::test(ListTypesDocumentsEleve::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->callAction('create', [
                'code' => 'ACTE_NAISSANCE',
                'nom' => 'Acte de naissance',
                'obligatoire' => true,
                'actif' => true,
            ]);

        $type = TypeDocumentEleve::where('code', 'ACTE_NAISSANCE')->firstOrFail();

        Livewire::test(ListEleves::class)
            ->callAction('create', [
                'nom' => 'Etoa',
                'prenom' => 'Merveille',
                'sexe' => 'F',
                'statut' => 'admis',
                'nom_tuteur' => 'Etoa Simon',
                'telephone_tuteur' => '671222333',
                'documentsAdmission' => [
                    [
                        'type_document_eleve_id' => $type->id,
                        'fichier' => [UploadedFile::fake()->create('acte.pdf', 20, 'application/pdf')],
                        'date_ajout' => today()->toDateString(),
                    ],
                ],
            ]);

        $eleve = Eleve::where('nom', 'Etoa')->firstOrFail();

        $document = DocumentEleve::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertSame($type->id, (int) $document->type_document_eleve_id);
        $this->assertNotEmpty($document->fichier);
        $this->assertSame($this->admin->id, (int) $document->ajoute_par);

        $this->assertTrue(
            $eleve->fresh()->documents_obligatoires_manquants->isEmpty(),
            'Le document obligatoire ajouté n’a pas été pris en compte.',
        );
    }

    public function test_impressions_d_inscription_et_de_facture_depuis_les_pages_filament(): void
    {
        $eleve = $this->eleveAdmissible('Nkoulou', 'Ariane');

        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => false,
            ]);

        $inscription = Inscription::withoutGlobalScopes()
            ->where('eleve_id', $eleve->id)
            ->where('annee_scolaire_id', $this->annee->id)
            ->firstOrFail();

        $this->get(route('impressions.inscriptions', [
            'ids' => (string) $inscription->id,
            'colonnes' => 'matricule,eleve,classe,statut',
        ]))->assertSuccessful()->assertSee($eleve->nom);

        $this->get(route('impressions.inscriptions.facture', ['inscription' => $inscription->id]))
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
