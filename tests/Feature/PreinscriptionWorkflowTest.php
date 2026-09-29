<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\CaisseServiceContract;
use App\Modules\Finances\Contracts\FacturePreinscriptionServiceContract;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use App\Modules\Finances\Exceptions\ValidationVersementInterditeException;
use App\Modules\Finances\Filament\Resources\FacturePreinscriptionResource\Pages\ListFacturesPreinscription;
use App\Modules\Finances\Mail\FactureProvisoireMail;
use App\Modules\Finances\Mail\InscriptionConfirmeeMail;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\ConfigurationFraisClasse;
use App\Modules\Finances\Models\FacturePreinscription;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\PaiementTrancheAllocation;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Contracts\InscriptionFacturationPort;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\CreateInscription;
use App\Modules\Scolarite\Filament\Resources\InscriptionResource\Pages\ListInscriptions;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Scolarite\Support\ElevePhoto;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Niveau;
use Database\Seeders\DocumentNumberingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PreinscriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_active_staff_account_can_start_an_inscription_but_a_suspended_account_cannot(): void
    {
        Role::firstOrCreate(['name' => 'Enseignant']);
        $personnelActif = User::factory()->create(['statut' => 'actif']);
        $personnelActif->assignRole('Enseignant');
        $personnelSuspendu = User::factory()->create(['statut' => 'suspendu']);
        $personnelSuspendu->assignRole('Enseignant');

        $this->actingAs($personnelActif);
        $this->assertTrue(InscriptionResource::canCreate());

        $this->actingAs($personnelSuspendu);
        $this->assertFalse(InscriptionResource::canCreate());
    }

    private Eleve $eleve;

    private ParentTuteur $parent;

    private Classe $classe;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $niveau = Niveau::create(['nom' => 'CM1', 'code' => 'CM1-PRE', 'ordre' => 5]);
        $this->annee = AnneeScolaire::create([
            'libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31', 'statut' => 'active',
        ]);
        $this->classe = Classe::create([
            'nom' => 'CM1 A', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $this->annee->id, 'capacite_max' => 30,
        ]);
        $this->eleve = Eleve::create(['nom' => 'Ndah', 'prenom' => 'Pearl', 'statut' => 'prospect']);
        $this->parent = ParentTuteur::create(['nom' => 'Ndah', 'prenom' => 'John', 'email' => 'john@example.test']);
        $this->eleve->parentsTuteurs()->attach($this->parent->id, ['responsable_paiement' => true]);

        foreach (['inscription' => 15000, 'transport' => 15000, 'cantine' => 10000] as $type => $montant) {
            GrilleFrais::create([
                'niveau_id' => $niveau->id,
                'annee_scolaire_id' => $this->annee->id,
                'type_frais' => $type,
                'montant' => $montant,
            ]);
        }
        FormatNumerotation::create(['type_document' => 'recu', 'format' => 'REC-{{annee}}-{{seq:4}}', 'prochain_numero' => 1]);
        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        app(CaisseServiceContract::class)->ouvrirSession(0);
    }

    public function test_preinscription_creates_pending_invoice_with_mandatory_and_selected_fees(): void
    {
        $id = app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id, ['transport'],
        );

        $this->assertDatabaseHas('inscriptions', ['id' => $id, 'statut' => 'en_attente_versement']);
        $facture = FacturePreinscription::with('lignes')->firstOrFail();
        $this->assertSame(30000.0, (float) $facture->montant_total);
        $this->assertEqualsCanonicalizing(['inscription', 'transport'], $facture->lignes->pluck('type_frais')->all());
        $this->assertNotNull($this->eleve->fresh()->matricule_permanent);
        $factureHtml = view('finances::pdf.facture-provisoire', [
            'facture' => $facture,
            'annee' => $this->annee,
        ])->render();
        $factureHtml = html_entity_decode($factureHtml);
        $this->assertStringContainsString('FACTURE', $factureHtml);
        $this->assertStringContainsString("Frais d'inscription", $factureHtml);
        $this->assertStringContainsString('Transport scolaire', $factureHtml);
        $this->assertStringContainsString('EN ATTENTE DE PAIEMENT', $factureHtml);
        $this->assertStringContainsString('En attente de versement', (new FactureProvisoireMail($facture))->render());
        $this->assertDatabaseHas('documents', [
            'documentable_type' => $facture->getMorphClass(),
            'documentable_id' => $facture->id,
            'categorie' => 'facture_provisoire',
        ]);
        Mail::assertSent(FactureProvisoireMail::class, fn (FactureProvisoireMail $mail): bool => $mail->facture->is($facture));
        $this->assertNotNull($facture->fresh()->envoyee_le);
    }

    public function test_only_selected_miscellaneous_fees_are_proposed_and_added(): void
    {
        $fraisDivers = CatalogueFraisDivers::create([
            'nom' => 'Transport scolaire optionnel',
            'categorie' => 'transport',
            'groupe_frais_id' => DB::table('groupes_frais')->where('code', 'autres')->value('id'),
            'montant_defaut' => 7000,
            'actif' => true,
        ]);

        $disponibles = app(InscriptionFacturationPort::class)->getFraisDisponibles(
            $this->classe->niveau_id,
            $this->annee->id,
        );
        $this->assertArrayHasKey('divers-'.$fraisDivers->id, $disponibles['optionnels']);
        $this->assertArrayNotHasKey('transport', $disponibles['optionnels']);
        $this->assertArrayNotHasKey('cantine', $disponibles['optionnels']);

        app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id,
            $this->classe->id,
            $this->annee->id,
            $this->parent->id,
            ['divers-'.$fraisDivers->id],
        );

        $this->assertDatabaseHas('facture_preinscription_lignes', [
            'type_frais' => 'divers-'.$fraisDivers->id,
            'libelle' => 'Transport scolaire optionnel',
            'montant' => 7000,
            'obligatoire' => false,
        ]);
        $this->assertDatabaseHas('frais_divers_eleves', [
            'catalogue_frais_divers_id' => $fraisDivers->id,
            'eleve_id' => $this->eleve->id,
            'statut' => 'actif',
        ]);
    }

    public function test_failed_invoice_email_is_not_marked_as_sent(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Transport indisponible'));

        app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id,
        );

        $facture = FacturePreinscription::firstOrFail();
        $this->assertNull($facture->fresh()->envoyee_le);
    }

    public function test_pending_invoice_email_can_be_retried(): void
    {
        app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id,
        );

        $facture = FacturePreinscription::firstOrFail();
        $facture->update(['envoyee_le' => null]);

        Mail::fake();
        app(FacturePreinscriptionServiceContract::class)
            ->renvoyerFactureProvisoire($facture->id);

        Mail::assertSent(FactureProvisoireMail::class);
        $this->assertNotNull($facture->fresh()->envoyee_le);
    }

    public function test_photo_field_keeps_camera_and_existing_file_choices_available(): void
    {
        $resource = file_get_contents(app_path('Modules/Scolarite/Filament/Resources/InscriptionResource.php'));

        $this->assertIsString($resource);
        $this->assertStringContainsString('Prendre une photo ou choisir un fichier', $resource);
        $this->assertStringContainsString('->imageEditor()', $resource);
        $this->assertStringNotContainsString('->capture(', $resource);
    }

    public function test_guided_registration_creates_student_parent_link_and_pending_invoice(): void
    {
        $id = app(InscriptionServiceInterface::class)->preparerEtDemarrerPreinscription(
            null,
            ['nom' => 'Mballa', 'prenom' => 'Grace', 'date_naissance' => '2018-04-12', 'sexe' => 'F', 'photo' => 'eleves/photos/grace.png'],
            null,
            ['nom' => 'Mballa', 'prenom' => 'Alice', 'telephone' => '690000000', 'email' => 'alice@example.test'],
            'mere',
            $this->classe->id,
            $this->annee->id,
            ['cantine'],
        );

        $this->assertDatabaseHas('inscriptions', ['id' => $id, 'statut' => 'en_attente_versement']);
        $this->assertDatabaseHas('eleves', ['nom' => 'Mballa', 'prenom' => 'Grace', 'photo' => 'eleves/photos/grace.png', 'statut' => 'prospect']);
        $this->assertDatabaseHas('parents_tuteurs', ['email' => 'alice@example.test']);
        $this->assertDatabaseHas('eleve_parent', ['lien' => 'mere', 'responsable_legal' => true, 'responsable_paiement' => true]);
        $this->assertDatabaseHas('factures_preinscription', ['statut' => 'en_attente_versement', 'montant_total' => 25000]);
    }

    public function test_staff_can_reach_confirmation_step_with_valid_billing_configuration(): void
    {
        Role::firstOrCreate(['name' => 'Enseignant']);
        $personnel = User::factory()->create(['statut' => 'actif']);
        $personnel->assignRole('Enseignant');
        $this->actingAs($personnel);

        Livewire::test(CreateInscription::class)
            ->fillForm([
                'eleve_existant' => true,
                'eleve_id' => $this->eleve->id,
            ])
            ->goToNextWizardStep()
            ->fillForm([
                'parent_existant' => true,
                'parent_id' => $this->parent->id,
                'lien_parente' => 'pere',
            ])
            ->goToNextWizardStep()
            ->fillForm([
                'annee_scolaire_id' => $this->annee->id,
                'classe_id' => $this->classe->id,
                'frais_optionnels' => ['transport'],
                'montant_verse' => 30000,
            ])
            ->goToNextWizardStep()
            ->assertHasNoFormErrors()
            ->assertSee('Après validation')
            ->assertSee('Frais calculés et montant disponible');
    }

    public function test_parent_declares_available_amount_while_invoice_total_is_calculated(): void
    {
        $id = app(InscriptionServiceInterface::class)->preparerEtDemarrerPreinscription(
            $this->eleve->id,
            [],
            $this->parent->id,
            [],
            'pere',
            $this->classe->id,
            $this->annee->id,
            ['transport'],
            30000,
        );

        $this->assertDatabaseHas('factures_preinscription', [
            'inscription_id' => $id,
            'montant_total' => 30000,
            'montant_versement_prevu' => 30000,
            'statut' => 'en_attente_versement',
        ]);
    }

    public function test_existing_student_form_keeps_selected_fees_and_declared_payment(): void
    {
        Role::firstOrCreate(['name' => 'Fondateur']);
        $personnel = User::factory()->create(['statut' => 'actif']);
        $personnel->assignRole('Fondateur');
        $this->actingAs($personnel);

        $fraisDivers = CatalogueFraisDivers::create([
            'nom' => 'Tenue scolaire',
            'categorie' => 'tenue',
            'groupe_frais_id' => DB::table('groupes_frais')->where('code', 'autres')->value('id'),
            'montant_defaut' => 7000,
            'actif' => true,
        ]);

        Livewire::test(CreateInscription::class)
            ->fillForm([
                'eleve_existant' => true,
                'eleve_id' => $this->eleve->id,
            ])
            ->assertSet('data.parent_existant', true)
            ->assertSet('data.parent_id', $this->parent->id)
            ->fillForm([
                'lien_parente' => 'pere',
                'annee_scolaire_id' => $this->annee->id,
                'classe_id' => $this->classe->id,
                'frais_optionnels' => ['divers-'.$fraisDivers->id],
                'montant_verse' => 15000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $facture = FacturePreinscription::with('lignes')->firstOrFail();
        $this->assertSame(22000.0, (float) $facture->montant_total);
        $this->assertSame(15000.0, (float) $facture->montant_versement_prevu);
        $this->assertEqualsCanonicalizing(
            ['inscription', 'divers-'.$fraisDivers->id],
            $facture->lignes->pluck('type_frais')->all(),
        );
        $this->assertDatabaseHas('inscriptions', [
            'eleve_id' => $this->eleve->id,
            'classe_id' => $this->classe->id,
            'statut' => 'en_attente_versement',
        ]);
    }

    public function test_existing_student_with_history_starts_a_reenrollment(): void
    {
        $ancienneAnnee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'brouillon',
        ]);
        $ancienneClasse = Classe::create([
            'nom' => 'CE2 A',
            'niveau_id' => $this->classe->niveau_id,
            'annee_scolaire_id' => $ancienneAnnee->id,
            'capacite_max' => 30,
        ]);
        Inscription::create([
            'eleve_id' => $this->eleve->id,
            'classe_id' => $ancienneClasse->id,
            'annee_scolaire_id' => $ancienneAnnee->id,
            'type' => 'inscription',
            'date_inscription' => '2026-09-01',
            'statut' => 'validee',
        ]);

        $id = app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id,
            $this->classe->id,
            $this->annee->id,
            $this->parent->id,
        );

        $this->assertDatabaseHas('inscriptions', [
            'id' => $id,
            'classe_id' => $this->classe->id,
            'type' => 'reinscription',
            'statut' => 'en_attente_versement',
        ]);
    }

    public function test_thirty_thousand_payment_covers_registration_then_first_class_slice(): void
    {
        $configuration = ConfigurationFraisClasse::create([
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'frais_inscription' => 15000,
            'montant_total' => 125000,
            'politique_validation_inscription' => 'frais_inscription',
            'montant_minimum_inscription' => 15000,
            'actif' => true,
        ]);
        $configuration->tranches()->createMany(collect(range(1, 5))->map(fn (int $ordre): array => [
            'ordre' => $ordre,
            'libelle' => "Tranche {$ordre}",
            'montant' => 25000,
            'date_echeance' => "2027-0{$ordre}-01",
            'actif' => true,
        ])->all());

        $inscriptionId = app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id,
            $this->classe->id,
            $this->annee->id,
            $this->parent->id,
            [],
            30000,
        );
        $facture = FacturePreinscription::firstOrFail();
        $this->assertSame(140000.0, (float) $facture->montant_total);

        Role::firstOrCreate(['name' => 'Comptable']);
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($comptable);

        app(FacturePreinscriptionServiceContract::class)->confirmerVersement(
            $facture->id,
            'bancaire',
            'VIR-30000',
            montant: 30000,
        );

        $this->assertDatabaseHas('paiements', [
            'inscription_id' => $inscriptionId,
            'rubrique' => 'inscription',
            'montant' => 15000,
        ]);
        $this->assertDatabaseHas('paiements', [
            'inscription_id' => $inscriptionId,
            'rubrique' => 'tranches_classe',
            'montant' => 15000,
        ]);
        $this->assertSame(15000.0, (float) PaiementTrancheAllocation::query()->sum('montant'));
        $this->assertDatabaseHas('inscriptions', ['id' => $inscriptionId, 'statut' => 'validee']);
    }

    public function test_missing_registration_fee_is_reported_by_the_billing_port(): void
    {
        $type = TypeFraisRecurrent::query()->where('nom', 'Inscription')->firstOrFail();
        GrilleFrais::query()->where('type_frais_recurrent_id', $type->id)->delete();
        $type->delete();

        $erreur = app(InscriptionFacturationPort::class)
            ->erreurConfiguration(
                (int) $this->classe->niveau_id,
                $this->annee->id,
            );

        $this->assertSame(
            "Les frais obligatoires d'inscription ne sont pas configurés pour ce niveau et cette année.",
            $erreur,
        );
    }

    public function test_student_photo_accepts_jpeg_and_png_within_five_megabytes(): void
    {
        foreach (['portrait.jpg', 'portrait.jpeg', 'portrait.png'] as $filename) {
            $validator = Validator::make(
                ['photo' => UploadedFile::fake()->image($filename)->size(ElevePhoto::MAX_SIZE_KO)],
                ['photo' => ['required', ...ElevePhoto::validationRules()]],
            );

            $this->assertFalse($validator->fails(), "La photo {$filename} devrait être acceptée.");
        }
    }

    public function test_student_photo_rejects_non_image_and_oversized_file(): void
    {
        $rules = ['photo' => ['required', ...ElevePhoto::validationRules()]];

        $this->assertTrue(Validator::make(
            ['photo' => UploadedFile::fake()->create('dossier.pdf', 100, 'application/pdf')],
            $rules,
        )->fails());
        $this->assertTrue(Validator::make(
            ['photo' => UploadedFile::fake()->image('portrait.jpg')->size(ElevePhoto::MAX_SIZE_KO + 1)],
            $rules,
        )->fails());
    }

    public function test_registration_list_toggles_between_native_cards_and_table(): void
    {
        $this->actingAs(User::factory()->create(['statut' => 'actif']));

        $component = Livewire::test(ListInscriptions::class)
            ->assertSet('affichage', 'cartes');

        $this->assertSame([
            'default' => 1,
            'sm' => 2,
            'md' => 2,
            'lg' => 3,
        ], $component->instance()->getTable()->getContentGrid());
        $component->call('toggleLayout')->assertSet('affichage', 'tableau');

        $this->assertNull($component->instance()->getTable()->getContentGrid());
    }

    // public function test_accounting_handoff_is_available_only_to_payment_validators(): void
    // {
    //     Role::firstOrCreate(['name' => 'Comptable']);
    //     $comptable = User::factory()->create(['statut' => 'actif']);
    //     $comptable->assignRole('Comptable');

    //     $this->actingAs($comptable);
    //     Livewire::test(ListInscriptions::class)
    //         ->assertActionVisible('passer_finances')
    //         ->assertSee('Passer aux finances');

    //     Role::firstOrCreate(['name' => 'Enseignant']);
    //     $enseignant = User::factory()->create(['statut' => 'actif']);
    //     $enseignant->assignRole('Enseignant');

    //     $this->actingAs($enseignant);
    //     Livewire::test(ListInscriptions::class)
    //         ->assertActionHidden('passer_finances');
    // }

    public function test_only_comptable_or_founder_can_confirm_then_registration_becomes_final(): void
    {
        $inscriptionId = app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id,
        );
        $facture = FacturePreinscription::firstOrFail();

        $this->actingAs(User::factory()->create(['statut' => 'actif']));
        try {
            app(FacturePreinscriptionServiceContract::class)->confirmerVersement($facture->id, 'bancaire', 'VIR-001');
            $this->fail('Un non-comptable ne doit pas confirmer le versement.');
        } catch (ValidationVersementInterditeException) {
            $this->assertDatabaseHas('factures_preinscription', ['id' => $facture->id, 'statut' => 'en_attente_versement']);
        }

        Role::firstOrCreate(['name' => 'Comptable']);
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($comptable);

        app(FacturePreinscriptionServiceContract::class)->confirmerVersement($facture->id, 'bancaire', 'VIR-001');

        $this->assertDatabaseHas('factures_preinscription', ['id' => $facture->id, 'statut' => 'payee', 'validee_par' => $comptable->id]);
        $this->assertDatabaseHas('inscriptions', ['id' => $inscriptionId, 'statut' => 'validee']);
        $this->assertNotNull($this->eleve->fresh()->matricule_permanent);
        $this->assertSame('inscrit', $this->eleve->fresh()->statut);
        $this->assertStringContainsString('Inscription confirmée', (new InscriptionConfirmeeMail($facture->fresh()))->render());
        Mail::assertSent(InscriptionConfirmeeMail::class);
        $this->assertDatabaseHas('documents', ['categorie' => 'recu_paiement']);
        $recu = app(PaiementServiceContract::class)
            ->getRecuPdf((int) $facture->fresh()->paiement_id);
        $this->assertStringStartsWith('%PDF', $recu['contenu']);
    }

    public function test_founder_can_confirm_a_pending_registration(): void
    {
        $inscriptionId = app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id,
        );
        Role::firstOrCreate(['name' => 'Fondateur']);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        app(FacturePreinscriptionServiceContract::class)->confirmerVersement(
            FacturePreinscription::firstOrFail()->id,
            'bancaire',
            'VIR-FONDATEUR',
        );

        $this->assertDatabaseHas('inscriptions', ['id' => $inscriptionId, 'statut' => 'validee']);
    }

    public function test_pending_registration_notifies_comptable_and_founder_in_app(): void
    {
        foreach (['Comptable', 'Fondateur'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
            User::factory()->create(['statut' => 'actif'])->assignRole($roleName);
        }

        app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id,
        );

        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'read_at' => null,
        ]);
    }

    public function test_cash_confirmation_displays_a_clear_success_message(): void
    {
        app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id,
        );
        $facture = FacturePreinscription::firstOrFail();

        Role::firstOrCreate(['name' => 'Comptable']);
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($comptable);

        Livewire::test(ListFacturesPreinscription::class)
            ->callTableAction('confirmer', $facture, ['mode' => 'especes'])
            ->assertDispatched('platform-state-updated')
            ->assertNotified('Paiement en espèces confirmé');

        $this->assertDatabaseHas('factures_preinscription', [
            'id' => $facture->id,
            'statut' => 'payee',
            'mode_paiement' => 'especes',
        ]);
    }

    public function test_invoice_and_receipt_documents_are_available_from_the_control_list(): void
    {
        app(InscriptionServiceInterface::class)->demarrerPreinscription(
            $this->eleve->id, $this->classe->id, $this->annee->id, $this->parent->id,
        );
        $facture = FacturePreinscription::firstOrFail();

        Role::firstOrCreate(['name' => 'Comptable']);
        $comptable = User::factory()->create(['statut' => 'actif']);
        $comptable->assignRole('Comptable');
        $this->actingAs($comptable);

        Livewire::test(ListFacturesPreinscription::class)
            ->assertTableActionVisible('voir_facture', $facture)
            ->assertTableActionHidden('voir_recu', $facture);

        app(FacturePreinscriptionServiceContract::class)
            ->confirmerVersement($facture->id, 'bancaire', 'VIR-PDF-001');

        Livewire::test(ListFacturesPreinscription::class)
            ->assertTableActionVisible('voir_facture', $facture->fresh())
            ->assertTableActionVisible('voir_recu', $facture->fresh());
    }

    public function test_receipt_numbering_seeder_is_idempotent_and_preserves_existing_configuration(): void
    {
        $existing = FormatNumerotation::query()->where('type_document', 'recu')->firstOrFail();
        $existing->update(['prochain_numero' => 42]);

        $this->seed(DocumentNumberingSeeder::class);
        $this->seed(DocumentNumberingSeeder::class);

        $this->assertSame(1, FormatNumerotation::query()->where('type_document', 'recu')->count());
        $this->assertSame(42, $existing->fresh()->prochain_numero);
    }
}
