<?php

declare(strict_types=1);

namespace Tests\Feature\Scolarite;

use App\Models\User;
use App\Modules\Finances\Contracts\ConfigurationFraisClasseServiceContract;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages\CreateEleve;
use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages\EditEleve;
use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages\ListEleves;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\CreateInscription;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Scolarite\Models\TypeDocumentEleve;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\DocumentNumberingSeeder;
use Database\Seeders\GroupesFraisSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdmissionEtInscriptionDirecteTest extends TestCase
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
            'capacite_max' => 30,
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

    public function test_admission_form_includes_two_contacts_and_emergency_contact(): void
    {
        $this->actingAs($this->fondateur);

        Livewire::test(CreateEleve::class)
            ->fillForm([
                'nom' => 'Kamga',
                'prenom' => 'David',
                'sexe' => 'M',
                'nom_tuteur' => 'Kamga Jean',
                'telephone_tuteur' => '677112233',
                'telephone_secondaire' => '699445566',
                'contact_urgence_nom' => 'Dr Njoya Paul - 655889900',
                'info_sms' => true,
                'derniere_classe_frequente' => 'SIL B',
                'numero_decision' => 'DEC-2026-001',
                'inscrire_immediatement' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $eleve = Eleve::where('nom', 'Kamga')->firstOrFail();
        $this->assertEquals('David', $eleve->prenom);
        $this->assertEquals('677112233', $eleve->telephone_tuteur);
        $this->assertEquals('699445566', $eleve->telephone_secondaire);
        $this->assertEquals('Dr Njoya Paul - 655889900', $eleve->contact_urgence_nom);
        $this->assertTrue($eleve->info_sms);
        $this->assertEquals('SIL B', $eleve->derniere_classe_frequente);
        $this->assertEquals('DEC-2026-001', $eleve->numero_decision);

        // Vérifier la synchronisation avec eleve_contacts_urgence
        $contactUrgence = $eleve->contactsUrgence()->first();
        $this->assertNotNull($contactUrgence);
        $this->assertEquals('Dr Njoya Paul - 655889900', $contactUrgence->nom);
        $this->assertEquals('655889900', $contactUrgence->telephone);
    }

    public function test_admission_with_immediate_registration_and_payment_validation(): void
    {
        $this->actingAs($this->fondateur);

        Livewire::test(CreateEleve::class)
            ->fillForm([
                'nom' => 'Mballa',
                'prenom' => 'Jeanne',
                'sexe' => 'F',
                'nom_tuteur' => 'Mballa Pierre',
                'telephone_tuteur' => '678123456',
                'telephone_secondaire' => '698654321',
                'contact_urgence_nom' => 'Mballa Sarah - 650112233',
                'info_sms' => false,
                'derniere_classe_frequente' => 'Maternelle 2',
                'inscrire_immediatement' => true,
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $eleve = Eleve::where('nom', 'Mballa')->firstOrFail();
        $this->assertEquals('inscrit', $eleve->statut);

        // Inscription doit exister et être validée
        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertEquals($this->classe->id, $inscription->classe_id);
        $this->assertEquals('validee', $inscription->statut);

        // Facture doit être payee
        $facture = FacturePreinscription::where('inscription_id', $inscription->id)->firstOrFail();
        $this->assertEquals('payee', $facture->statut);

        // Paiement doit être enregistré
        $paiement = Paiement::where('inscription_id', $inscription->id)->first();
        $this->assertNotNull($paiement);
        $this->assertEquals(15000, $paiement->montant);
        $this->assertEquals('valide', $paiement->statut);
    }

    public function test_inscrire_modal_action_registers_and_validates_admitted_student(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Tchoupo',
            'prenom' => 'Samuel',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Tchoupo Marc',
            'telephone_tuteur' => '675556677',
        ]);

        Livewire::test(ListEleves::class)
            ->assertTableActionExists('inscrire', record: $eleve)
            ->callTableAction('inscrire', $eleve, data: [
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasNoTableActionErrors();

        $eleve->refresh();
        $this->assertEquals('inscrit', $eleve->statut);

        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertEquals('validee', $inscription->statut);
        $this->assertEquals($this->annee->id, $inscription->annee_scolaire_id);
        $this->assertEquals($this->classe->id, $inscription->classe_id);
    }

    public function test_inscrire_header_modal_action_on_edit_page(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Kenfack',
            'prenom' => 'Boris',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Kenfack Alain',
            'telephone_tuteur' => '670001122',
        ]);

        Livewire::test(EditEleve::class, ['record' => $eleve->id])
            ->assertActionExists('inscrire')
            ->callAction('inscrire', data: [
                'classe_id' => $this->classe->id,
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasNoActionErrors();

        $eleve->refresh();
        $this->assertEquals('inscrit', $eleve->statut);

        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertEquals('validee', $inscription->statut);
    }

    public function test_create_inscription_prefills_admitted_student_and_registers_for_active_year(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Kenfack',
            'prenom' => 'Boris',
            'sexe' => 'M',
            'statut' => 'admis',
            'nom_tuteur' => 'Kenfack Alain',
            'telephone_tuteur' => '670001122',
        ]);

        request()->merge(['eleve_id' => $eleve->id]);

        Livewire::withQueryParams(['eleve_id' => $eleve->id])
            ->test(CreateInscription::class, ['eleve_id' => $eleve->id])
            ->assertSet('data.eleve_id', $eleve->id)
            ->goToNextWizardStep()
            ->fillForm([
                'annee_scolaire_id' => $this->annee->id,
                'classe_id' => $this->classe->id,
            ])
            ->goToNextWizardStep()
            ->fillForm([
                'montant_verse' => 15000,
            ])
            ->goToNextWizardStep()
            ->call('create')
            ->assertHasNoFormErrors();

        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertEquals($this->annee->id, $inscription->annee_scolaire_id);
        $this->assertEquals($this->classe->id, $inscription->classe_id);
        $this->assertEquals('en_attente_versement', $inscription->statut);
    }

    public function test_inscriptions_can_be_filtered_by_annee_scolaire(): void
    {
        $this->actingAs($this->fondateur);

        Livewire::test(ListInscriptions::class)
            ->assertTableFilterExists('annee_scolaire_id');
    }

    public function test_pieces_du_dossier_sont_disponibles_et_peuvent_etre_ajoutees_a_ladmission(): void
    {
        $this->actingAs($this->fondateur);

        // Vérifier que les types de documents par défaut sont bien semés et actifs
        $types = TypeDocumentEleve::query()->where('actif', true)->get();
        $this->assertGreaterThanOrEqual(6, $types->count());
        $this->assertTrue($types->contains('code', 'ACTE_NAISSANCE'));
        $this->assertTrue($types->contains('code', 'PHOTO_IDENTITE'));

        $typeActe = $types->firstWhere('code', 'ACTE_NAISSANCE');

        Storage::fake('public');
        $fakeFile = UploadedFile::fake()->create('acte_naissance.pdf', 100, 'application/pdf');

        Livewire::test(CreateEleve::class)
            ->fillForm([
                'nom' => 'Nguemo',
                'prenom' => 'Claire',
                'sexe' => 'F',
                'nom_tuteur' => 'Nguemo Martin',
                'telephone_tuteur' => '677998877',
                'documentsAdmission' => [
                    [
                        'type_document_eleve_id' => $typeActe->id,
                        'fichier' => [$fakeFile],
                        'date_ajout' => '2026-09-27',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $eleve = Eleve::where('nom', 'Nguemo')->firstOrFail();
        $this->assertCount(1, $eleve->documentsAdmission);
        $this->assertEquals($typeActe->id, $eleve->documentsAdmission->first()->type_document_eleve_id);
    }

    public function test_sexe_est_obligatoire_et_statistiques_garcons_filles_sont_exactes(): void
    {
        $this->actingAs($this->fondateur);

        // Validation du sexe obligatoire lors de l'admission
        Livewire::test(CreateEleve::class)
            ->fillForm([
                'nom' => 'TestSexe',
                'prenom' => 'SansSexe',
                'sexe' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['sexe' => 'required']);

        // Créer 1 garçon et 2 filles
        Eleve::create(['nom' => 'G1', 'prenom' => 'Boy', 'sexe' => 'M', 'statut' => 'admis']);
        Eleve::create(['nom' => 'F1', 'prenom' => 'Girl One', 'sexe' => 'F', 'statut' => 'admis']);
        Eleve::create(['nom' => 'F2', 'prenom' => 'Girl Two', 'sexe' => 'F', 'statut' => 'inscrit']);

        // Vérifier le rendu de la liste et des statistiques
        Livewire::test(ListEleves::class)
            ->assertSuccessful()
            ->assertSee('Garçons')
            ->assertSee('Filles')
            ->assertSee('Total admis');

        $this->assertEquals(1, Eleve::where('sexe', 'M')->count());
        $this->assertEquals(2, Eleve::where('sexe', 'F')->count());
    }

    public function test_successive_admissions_generate_unique_matricules_and_avoid_collisions(): void
    {
        $this->actingAs($this->fondateur);

        // Premier élève
        Livewire::test(CreateEleve::class)
            ->fillForm([
                'nom' => 'ElevePremier',
                'prenom' => 'Un',
                'sexe' => 'M',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $e1 = Eleve::where('nom', 'ElevePremier')->firstOrFail();
        $this->assertNotEmpty($e1->matricule_permanent);

        // Deuxième élève consécutif
        Livewire::test(CreateEleve::class)
            ->fillForm([
                'nom' => 'EleveDeuxieme',
                'prenom' => 'Deux',
                'sexe' => 'F',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $e2 = Eleve::where('nom', 'EleveDeuxieme')->firstOrFail();
        $this->assertNotEmpty($e2->matricule_permanent);
        $this->assertNotEquals($e1->matricule_permanent, $e2->matricule_permanent);

        // Simulation d'un compteur désynchronisé en arrière : la génération doit sauter les matricules existants sans erreur
        DB::table('formats_numerotation')
            ->where('type_document', 'matricule_eleve')
            ->update(['prochain_numero' => 1]);

        Livewire::test(CreateEleve::class)
            ->fillForm([
                'nom' => 'EleveTroisieme',
                'prenom' => 'Trois',
                'sexe' => 'M',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $e3 = Eleve::where('nom', 'EleveTroisieme')->firstOrFail();
        $this->assertNotEmpty($e3->matricule_permanent);
        $this->assertNotEquals($e1->matricule_permanent, $e3->matricule_permanent);
        $this->assertNotEquals($e2->matricule_permanent, $e3->matricule_permanent);
    }

    public function test_classes_autorisees_proposees_a_partir_de_la_derniere_classe_frequente(): void
    {
        $cycle = Cycle::first() ?? Cycle::create(['code' => 'TEST_CYC', 'nom' => 'Cycle Test', 'ordre' => 1]);

        $niveau6e = Niveau::create(['code' => '6E_T', 'nom' => '6e', 'cycle_id' => $cycle->id, 'ordre' => 10]);
        $niveau5e = Niveau::create(['code' => '5E_T', 'nom' => '5e', 'cycle_id' => $cycle->id, 'ordre' => 20]);
        $niveau4e = Niveau::create(['code' => '4E_T', 'nom' => '4e', 'cycle_id' => $cycle->id, 'ordre' => 30]);

        $classe6e = Classe::create([
            'code' => '6E-T-A',
            'nom' => '6e Test',
            'niveau_id' => $niveau6e->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
            'frais' => 100_000,
        ]);
        $classe5e = Classe::create([
            'code' => '5E-T-A',
            'nom' => '5e Test',
            'niveau_id' => $niveau5e->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
            'frais' => 100_000,
        ]);
        $classe4e = Classe::create([
            'code' => '4E-T-A',
            'nom' => '4e Test',
            'niveau_id' => $niveau4e->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
            'frais' => 100_000,
        ]);

        // Élève ayant fréquenté la 5e, non redoublant (passage en 4e)
        $elevePassage = Eleve::create([
            'nom' => 'Passage',
            'prenom' => 'Paul',
            'sexe' => 'M',
            'statut' => 'admis',
            'derniere_classe_frequente' => '5e',
            'redoublant' => false,
            'matricule_permanent' => 'AMB-TEST-PASS-01',
        ]);

        $options = InscriptionResource::classesAutoriseesPourEleve($elevePassage->id, $this->annee->id);

        // 6e (ordre 10) et CP (ordre 1) doivent être exclus car inférieurs à la 5e (ordre 20)
        $this->assertArrayNotHasKey($classe6e->id, $options);
        $this->assertArrayNotHasKey($this->classe->id, $options);
        // 5e et 4e doivent être présentes (à partir de sa classe)
        $this->assertArrayHasKey($classe5e->id, $options);
        $this->assertArrayHasKey($classe4e->id, $options);

        // La classe recommandée pour un non redoublant issu de la 5e est le niveau supérieur (4e)
        $recommandeePassage = InscriptionResource::classeRecommandeePourEleve($elevePassage->id, $this->annee->id);
        $this->assertEquals($classe4e->id, $recommandeePassage);

        // Élève ayant fréquenté la 5e, redoublant
        $eleveRedoublant = Eleve::create([
            'nom' => 'Redoublant',
            'prenom' => 'Pierre',
            'sexe' => 'M',
            'statut' => 'admis',
            'derniere_classe_frequente' => '5e',
            'redoublant' => true,
            'matricule_permanent' => 'AMB-TEST-RED-01',
        ]);

        $recommandeeRedoublement = InscriptionResource::classeRecommandeePourEleve($eleveRedoublant->id, $this->annee->id);
        $this->assertEquals($classe5e->id, $recommandeeRedoublement);
    }

    public function test_derniere_classe_frequente_is_displayed_in_admissions_table(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Fokou',
            'prenom' => 'Alain',
            'sexe' => 'M',
            'statut' => 'admis',
            'derniere_classe_frequente' => '6e',
            'matricule_permanent' => 'AMB-TEST-6E-01',
        ]);

        Livewire::test(ListEleves::class)
            ->assertTableColumnExists('derniere_classe_frequente')
            ->assertCanSeeTableRecords([$eleve]);
    }

    public function test_nouvelle_inscription_modal_action_in_list_inscriptions(): void
    {
        $this->actingAs($this->fondateur);

        $eleve = Eleve::create([
            'nom' => 'Atangana',
            'prenom' => 'Sylvie',
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => 'Atangana Joseph',
            'telephone_tuteur' => '677998877',
            'derniere_classe_frequente' => 'CP',
            'matricule_permanent' => 'AMB-TEST-SYL-01',
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

        $eleve->refresh();
        $this->assertEquals('inscrit', $eleve->statut);
        $this->assertTrue(EleveResource::estInscritAnneeActive($eleve));
    }

    public function test_inscription_with_frais_divers_and_print_routes(): void
    {
        $this->actingAs($this->fondateur);

        // Ensure default diverse fees
        app(InscriptionFacturationPort::class)->assurerFraisDiversParDefaut();
        $fraisTenue = CatalogueFraisDivers::where('nom', 'Tenue scolaire')->firstOrFail();

        $eleve = Eleve::create([
            'nom' => 'Mballa',
            'prenom' => 'Jeanne',
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => 'Mballa Pierre',
            'telephone_tuteur' => '699112233',
            'derniere_classe_frequente' => 'CP',
            'matricule_permanent' => 'AMB-TEST-MBAL-01',
        ]);

        // Attempt with insufficient amount (15 000 FCFA while 40 000 FCFA is required) -> must fail
        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'frais_optionnels' => ['divers-'.$fraisTenue->id],
                'montant_verse' => 15000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasActionErrors(['montant_verse']);

        // Now pay required minimum (40 000 FCFA = 15k inscription + 25k tenue)
        Livewire::test(ListInscriptions::class)
            ->callAction('create', [
                'eleve_id' => $eleve->id,
                'classe_id' => $this->classe->id,
                'frais_optionnels' => ['divers-'.$fraisTenue->id],
                'montant_verse' => 40000,
                'mode_paiement' => 'especes',
                'valider_paiement' => true,
            ])
            ->assertHasNoActionErrors();

        $inscription = Inscription::where('eleve_id', $eleve->id)->firstOrFail();

        // 1. Verify parent principal attributes
        $this->assertEquals('Mballa Pierre', $inscription->parent_principal_nom);
        $this->assertEquals('699112233', $inscription->parent_principal_telephone);

        // 2. Verify frais divers attached
        $divers = $inscription->frais_divers_souscrits;
        $this->assertNotEmpty($divers);
        $this->assertEquals('Tenue scolaire', $divers[0]['nom']);
        $this->assertEquals(25000.0, (float) $divers[0]['montant']);

        // 3. Verify receipt route works and returns PDF
        $responseRecu = $this->get(route('impressions.inscriptions.recu', ['inscription' => $inscription->id]));
        $responseRecu->assertStatus(200);
        $responseRecu->assertHeader('Content-Type', 'application/pdf');

        // 4. Verify invoice route works and returns PDF
        $responseFacture = $this->get(route('impressions.inscriptions.facture', ['inscription' => $inscription->id]));
        $responseFacture->assertStatus(200);
        $responseFacture->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_inscriptions_table_columns_parent_and_frais_divers_removed(): void
    {
        $this->actingAs($this->fondateur);

        // Ces colonnes ont été supprimées de la liste des inscrits (trop verbeux, accessible via la fiche)
        Livewire::test(ListInscriptions::class)
            ->assertTableColumnDoesNotExist('parent_principal')
            ->assertTableColumnDoesNotExist('frais_divers')
            ->assertTableColumnDoesNotExist('anneeScolaire.libelle')
            ->assertTableColumnDoesNotExist('type');
    }
}
