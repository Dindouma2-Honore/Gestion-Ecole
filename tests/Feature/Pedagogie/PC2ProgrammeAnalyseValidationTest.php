<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\DocumentExtractor;
use App\Modules\Pedagogie\Contracts\ProgramDocumentAnalyzer;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Jobs\AnalyseProgramme;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Services\ProgramImportService;
use App\Modules\Pedagogie\Services\ProgramStructureService;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Document;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PC2ProgrammeAnalyseValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $userEnseignant;

    private Enseignant $enseignant;

    private AnneeScolaire $annee;

    private Classe $classe;

    private OffrePedagogique $offre;

    private AffectationPedagogique $affectation;

    private Matiere $matiere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
        Storage::fake('documents');

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $cycle = Cycle::create(['code' => 'PRI-PC2', 'nom' => 'Primaire', 'ordre' => 1]);
        $niveau = Niveau::create(['cycle_id' => $cycle->id, 'code' => 'CM1-PC2', 'nom' => 'CM1', 'ordre' => 1]);

        $this->classe = Classe::create([
            'nom' => 'CM1 A',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
        ]);

        $this->userEnseignant = User::factory()->create([
            'statut' => 'actif',
            'niveau_id' => null,
        ]);
        $this->userEnseignant->assignRole('Enseignant');
        Permission::findOrCreate('module.pedagogie', 'web');
        $this->userEnseignant->givePermissionTo('module.pedagogie');

        $employe = Employe::create([
            'user_id' => $this->userEnseignant->id,
            'matricule' => 'E-PC2-01',
            'nom' => 'Ondoa',
            'prenom' => 'Pauline',
            'poste' => 'Enseignant',
            'departement' => 'Pédagogie',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        $this->enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'statut_contractuel' => 'titulaire',
        ]);

        $this->matiere = Matiere::create([
            'nom' => 'Sciences et Technologies',
            'code' => 'SCI-PC2',
            'nature' => 'matiere',
            'coefficient' => 2,
            'coefficient_defaut' => 2,
            'actif' => true,
        ]);

        $this->offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'coefficient_matiere' => 2,
            'actif' => true,
        ]);

        $this->affectation = AffectationPedagogique::create([
            'enseignant_id' => $this->enseignant->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'principal',
            'actif' => true,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ]);

        $this->actingAs($this->userEnseignant);
    }

    private function creerDocumentWord(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'programme').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Unité 1 : Le monde vivant</w:t></w:r></w:p><w:p><w:r><w:t>Leçon 1 : Les végétaux</w:t></w:r></w:p><w:p><w:r><w:t>Objectif : Identifier les espèces végétales</w:t></w:r></w:p><w:p><w:r><w:t>Unité 2 : Les matières</w:t></w:r></w:p><w:p><w:r><w:t>Leçon 1 : Les états de l\'eau</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'programme_sciences.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    public function test_pc2_1_import_programme_affectation_et_ged_privee(): void
    {
        // 1. L'enseignant affecté importe le fichier
        $fichier = $this->creerDocumentWord();
        $importService = app(ProgramImportService::class);

        $programme = $importService->importer(
            $this->annee->id,
            $this->classe->id,
            $this->offre->id,
            $fichier
        );

        $this->assertInstanceOf(Programme::class, $programme);
        $this->assertEquals('brouillon', $programme->statut);
        $this->assertEquals('analyse_en_attente', $programme->analyse_statut);
        $this->assertEquals($this->enseignant->id, $programme->enseignant_id);
        $this->assertEquals($this->offre->id, $programme->offre_pedagogique_id);
        $this->assertNotNull($programme->document_source_id);

        // 2. Vérification GED privée : document restreint, catégorie programme_source
        $doc = Document::find($programme->document_source_id);
        $this->assertNotNull($doc);
        $this->assertEquals('programme_source', $doc->categorie);
        $this->assertEquals('restreint', $doc->visibilite ?? 'restreint');

        // 3. Job d'analyse dispatché de manière asynchrone
        Queue::assertPushed(AnalyseProgramme::class, function ($job) use ($programme) {
            return $job->programmeId === $programme->id;
        });

        // 4. Tentative d'import par un utilisateur non affecté -> refusé 403
        $autreUser = User::factory()->create(['statut' => 'actif']);
        $autreUser->assignRole('Enseignant');
        $autreUser->givePermissionTo('module.pedagogie');
        $this->actingAs($autreUser);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Cette offre ne relève pas de vos affectations actives.');

        $importService->importer(
            $this->annee->id,
            $this->classe->id,
            $this->offre->id,
            $this->creerDocumentWord()
        );
    }

    public function test_pc2_2_analyse_ia_asynchrone_et_proposition_structuration_sans_auto_validation(): void
    {
        $fichier = $this->creerDocumentWord();
        $programme = app(ProgramImportService::class)->importer(
            $this->annee->id,
            $this->classe->id,
            $this->offre->id,
            $fichier
        );

        // Exécution de l'analyse IA via le Job
        $job = new AnalyseProgramme($programme->id);
        $job->handle(
            app(DocumentExtractor::class),
            app(ProgramDocumentAnalyzer::class),
            app(DocumentServiceContract::class)
        );

        $programme->refresh();

        // Statut d'analyse passé à a_valider
        $this->assertEquals('a_valider', $programme->analyse_statut);

        // INVARIANT CRUCIAL : L'analyse IA NE VALIDE PAS le programme
        $this->assertEquals('brouillon', $programme->statut);
        $this->assertNull($programme->valide_le);
        $this->assertNull($programme->valide_par);

        // La structure proposée (chapitres / leçons) est créée
        $this->assertGreaterThan(0, $programme->chapitres()->count());
        $proposition = app(ProgramStructureService::class)->proposition($programme);
        $this->assertIsArray($proposition);
        $this->assertNotEmpty($proposition);
        $this->assertArrayHasKey('titre', $proposition[0]);
    }

    public function test_pc2_3_correction_humaine_de_la_structure(): void
    {
        $fichier = $this->creerDocumentWord();
        $programme = app(ProgramImportService::class)->importer(
            $this->annee->id,
            $this->classe->id,
            $this->offre->id,
            $fichier
        );

        (new AnalyseProgramme($programme->id))->handle(
            app(DocumentExtractor::class),
            app(ProgramDocumentAnalyzer::class),
            app(DocumentServiceContract::class)
        );

        $structureService = app(ProgramStructureService::class);
        $proposition = $structureService->proposition($programme);

        // L'enseignant / coordinateur apporte des modifications
        $proposition[0]['titre'] = 'Unité 1 : Le monde vivant et ses écosystèmes (Modifié)';
        $proposition[0]['volume_horaire_prevu'] = 8.5;

        // Enregistrement des modifications sans validation finale ($valider = false)
        $programmeModifie = $structureService->enregistrer($programme, $proposition, false);

        $this->assertEquals('a_valider', $programmeModifie->analyse_statut);
        $this->assertEquals('brouillon', $programmeModifie->statut);
        $this->assertNull($programmeModifie->valide_le);

        // Vérification de la persistance en base
        $this->assertDatabaseHas('programme_chapitres', [
            'programme_id' => $programme->id,
            'titre' => 'Unité 1 : Le monde vivant et ses écosystèmes (Modifié)',
            'volume_horaire_prevu' => 8.5,
        ]);
    }

    public function test_pc2_4_validation_humaine_explicite_et_publication(): void
    {
        $fichier = $this->creerDocumentWord();
        $programme = app(ProgramImportService::class)->importer(
            $this->annee->id,
            $this->classe->id,
            $this->offre->id,
            $fichier
        );

        (new AnalyseProgramme($programme->id))->handle(
            app(DocumentExtractor::class),
            app(ProgramDocumentAnalyzer::class),
            app(DocumentServiceContract::class)
        );

        $structureService = app(ProgramStructureService::class);
        $programmeService = app(ProgrammeServiceInterface::class);
        $proposition = $structureService->proposition($programme);

        // 1. Impossible de publier un programme non validé
        try {
            $programmeService->publier($programme->id);
            $this->fail('La publication d\'un programme non validé aurait dû lever une exception.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        // 2. Validation humaine explicite ($valider = true)
        $programmeValide = $structureService->enregistrer($programme, $proposition, true);

        $this->assertEquals('valide', $programmeValide->statut);
        $this->assertEquals('valide', $programmeValide->analyse_statut);
        $this->assertNotNull($programmeValide->valide_le);
        $this->assertEquals($this->userEnseignant->id, $programmeValide->valide_par);

        // 3. Publication après validation
        $resultatPublication = $programmeService->publier($programmeValide->id);
        $this->assertEquals('publie', $resultatPublication['statut']);

        $programmeValide->refresh();
        $this->assertEquals('publie', $programmeValide->statut);
    }

    public function test_pc2_5_securite_analyser_controller_non_fail_open(): void
    {
        $fichier = $this->creerDocumentWord();
        $programme = app(ProgramImportService::class)->importer(
            $this->annee->id,
            $this->classe->id,
            $this->offre->id,
            $fichier
        );

        // 1. Non authentifié -> 401 Unauthorized
        auth()->logout();
        $responseUnauth = $this->postJson("/pedagogie/programmes/{$programme->id}/analyser");
        $responseUnauth->assertStatus(401);

        // 2. Utilisateur non autorisé (ni propriétaire, ni direction, ni supervision) -> 403 Forbidden
        $intrus = User::factory()->create(['statut' => 'actif']);
        $intrus->assignRole('Enseignant');
        $this->actingAs($intrus);

        $responseForbidden = $this->postJson("/pedagogie/programmes/{$programme->id}/analyser");
        $responseForbidden->assertStatus(403);
        $responseForbidden->assertJson(['error' => 'Action non autorisée sur ce programme.']);

        // 3. Direction / Fondateur -> Autorisé (200 OK)
        $directeur = User::factory()->create(['statut' => 'actif']);
        $directeur->assignRole('Fondateur');
        $this->actingAs($directeur);

        $responseSuccess = $this->postJson("/pedagogie/programmes/{$programme->id}/analyser");
        $responseSuccess->assertStatus(200);
        $responseSuccess->assertJson(['success' => true]);
    }
}
