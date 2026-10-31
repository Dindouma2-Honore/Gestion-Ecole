<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Contracts\ProgressionServiceInterface;
use App\Modules\Pedagogie\Enums\StatutProgrammeEnum;
use App\Modules\Pedagogie\Jobs\ProcessProgrammeDocumentJob;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\Devoir;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\Pedagogie\Models\ProgrammeLecon;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Pedagogie\Services\ProgressionCalculator;
use App\Modules\Pedagogie\Services\ProgressionMonitoringService;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\RH\Services\EnseignantService;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Contracts\ParametrageServiceContract;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProgrammesEtProgressionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $anneeId = 1;
    private Matiere $matiere;
    private int $enseignantId = 10;
    private User $userEnseignant;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('annees_scolaires')->insertOrIgnore([
            'id' => $this->anneeId,
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);

        $this->matiere = Matiere::create([
            'code' => 'MATH_PROG',
            'nom' => 'Mathématiques Modernes',
            'actif' => true,
        ]);

        $this->userEnseignant = User::factory()->create([
            'id' => $this->enseignantId,
            'email' => 'prof.prog@ambassadors.test',
        ]);
        $this->actingAs($this->userEnseignant);

        // Mock EnseignantService si nécessaire
        $mockEns = $this->createMock(EnseignantServiceInterface::class);
        $mockEns->method('getEnseignantIdPourUtilisateur')->willReturn($this->enseignantId);
        $mockEns->method('estAffecteA')->willReturn(true);
        $this->app->instance(EnseignantServiceInterface::class, $mockEns);

        // Mock AnneeScolaireService
        $mockAnnee = $this->createMock(AnneeScolaireServiceContract::class);
        $mockAnnee->method('getAnneeCouranteId')->willReturn($this->anneeId);
        $mockAnnee->method('ecritureAutorisee')->willReturn(true);
        $mockAnnee->method('getAnneeCourante')->willReturn((object) ['id' => $this->anneeId, 'libelle' => '2026-2027']);
        $mockAnnee->method('getAnneeScolaire')->willReturn((object) [
            'id' => $this->anneeId,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
        ]);
        $this->app->instance(AnneeScolaireServiceContract::class, $mockAnnee);
    }

    public function test_import_programme_cree_statut_analyse_en_attente_et_dispatche_job(): void
    {
        Queue::fake();

        $offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'actif' => true,
        ]);

        AffectationPedagogique::create([
            'enseignant_id' => $this->enseignantId,
            'classe_id' => 1,
            'matiere_id' => $this->matiere->id,
            'offre_pedagogique_id' => $offre->id,
            'annee_scolaire_id' => $this->anneeId,
            'type_affectation' => 'matiere',
            'actif' => true,
        ]);

        $fichier = UploadedFile::fake()->create('programme_maths.pdf', 100, 'application/pdf');

        /** @var ProgrammeServiceInterface $service */
        $service = app(ProgrammeServiceInterface::class);
        $programme = $service->importerProgrammeDocument($this->enseignantId, $offre->id, $fichier);

        $this->assertDatabaseHas('programmes', [
            'id' => $programme->id,
            'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
            'enseignant_id' => $this->enseignantId,
            'offre_pedagogique_id' => $offre->id,
            'version' => 1,
        ]);

        Queue::assertPushed(ProcessProgrammeDocumentJob::class, function ($job) use ($programme) {
            return $job->programmeId === $programme->id;
        });
    }

    public function test_interdiction_import_si_enseignant_non_affecte_a_l_offre(): void
    {
        $offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 2,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'actif' => true,
        ]);

        $fichier = UploadedFile::fake()->create('programme_maths.pdf', 100, 'application/pdf');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Vous n'êtes pas affecté à cette offre pédagogique.");

        app(ProgrammeServiceInterface::class)->importerProgrammeDocument(999, $offre->id, $fichier);
    }

    public function test_enseignant_ne_voit_que_les_offres_auxquelles_il_est_affecte(): void
    {
        $employe = Employe::create([
            'user_id' => $this->userEnseignant->id,
            'matricule' => 'ENS-PROG-REAL',
            'nom' => 'Test',
            'prenom' => 'Enseignant',
            'poste' => 'Enseignant',
            'date_embauche' => '2024-09-01',
            'statut' => 'actif',
        ]);
        $enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'statut_contractuel' => 'titulaire',
        ]);
        $this->enseignantId = $enseignant->id;
        $this->app->instance(EnseignantServiceInterface::class, app(EnseignantService::class));

        $offreAffectee = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'actif' => true,
        ]);
        $autreMatiere = Matiere::create([
            'code' => 'FR_PROG',
            'nom' => 'Français',
            'actif' => true,
        ]);
        $offreNonAffectee = OffrePedagogique::create([
            'matiere_id' => $autreMatiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'actif' => true,
        ]);

        AffectationPedagogique::create([
            'enseignant_id' => $this->enseignantId,
            'classe_id' => 1,
            'matiere_id' => $this->matiere->id,
            'offre_pedagogique_id' => $offreAffectee->id,
            'annee_scolaire_id' => $this->anneeId,
            'type_affectation' => 'matiere',
            'actif' => true,
        ]);

        $offres = ProgrammeResource::offresAffectees(1);

        $this->assertArrayHasKey($offreAffectee->id, $offres);
        $this->assertArrayNotHasKey($offreNonAffectee->id, $offres);
    }

    public function test_profil_administratif_conserve_l_acces_aux_offres_existantes(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Fondateur', 'guard_name' => 'web']);
        $this->userEnseignant->assignRole('Fondateur');

        $offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'actif' => true,
        ]);

        $this->assertArrayHasKey($offre->id, ProgrammeResource::offresAffectees(1));

        Queue::fake();
        $fichier = UploadedFile::fake()->create('programme_maths.pdf', 100, 'application/pdf');
        $programme = app(ProgrammeServiceInterface::class)->importerProgrammeDocument($this->enseignantId, $offre->id, $fichier);

        $this->assertDatabaseHas('programmes', [
            'id' => $programme->id,
            'offre_pedagogique_id' => $offre->id,
        ]);
    }

    public function test_job_analyse_document_et_passe_en_a_valider(): void
    {
        $programme = Programme::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'enseignant_id' => $this->enseignantId,
            'titre' => 'Programme Test',
            'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
            'version' => 1,
        ]);

        // Création d'un fichier texte temporaire mimant un PDF extrait
        $tempFile = tempnam(sys_get_temp_dir(), 'test_prog_').'.txt';
        $contenu = <<<'TXT'
        Chapitre 1 : Arithmétique modulaire
        Objectif : Arithmétique dans Z
        Leçon 1 : Division euclidienne (2h)
        Leçon 2 : Théorème de Gauss (3h)

        Chapitre 2 : Géométrie plane
        Leçon 1 : Vecteurs et repérage (2h)
        TXT;
        file_put_contents($tempFile, $contenu);

        // Simulation de l'analyzer via mock ou appel direct de la méthode d'interprétation
        $analyzerMock = $this->createMock(\App\Modules\Pedagogie\Services\ProgramDocumentAnalyzer::class);
        $analyzerMock->method('analyserFichier')->willReturn([
            [
                'titre' => 'Chapitre 1 : Arithmétique modulaire',
                'type_structure' => 'chapitre',
                'ordre' => 1,
                'objectif' => 'Arithmétique dans Z',
                'volume_horaire_prevu' => 5.0,
                'lecons' => [
                    ['titre' => 'Leçon 1 : Division euclidienne', 'type_element' => 'lecon', 'ordre' => 1, 'volume_horaire_prevu' => 2.0, 'objectifs' => null],
                    ['titre' => 'Leçon 2 : Théorème de Gauss', 'type_element' => 'lecon', 'ordre' => 2, 'volume_horaire_prevu' => 3.0, 'objectifs' => null],
                ],
            ],
            [
                'titre' => 'Chapitre 2 : Géométrie plane',
                'type_structure' => 'chapitre',
                'ordre' => 2,
                'objectif' => null,
                'volume_horaire_prevu' => 2.0,
                'lecons' => [
                    ['titre' => 'Leçon 1 : Vecteurs et repérage', 'type_element' => 'lecon', 'ordre' => 1, 'volume_horaire_prevu' => 2.0, 'objectifs' => null],
                ],
            ],
        ]);

        $job = new ProcessProgrammeDocumentJob($programme->id, $tempFile, 'secondaire');
        $job->handle($analyzerMock);

        $programme->refresh();
        $this->assertEquals(StatutProgrammeEnum::A_VALIDER->value, $programme->statut);
        $this->assertCount(2, $programme->chapitres);
        $this->assertEquals(3, ProgrammeLecon::count());

        unlink($tempFile);
    }

    public function test_job_gere_echec_document_illisible_et_passe_en_echec_analyse(): void
    {
        $programme = Programme::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'enseignant_id' => $this->enseignantId,
            'titre' => 'Programme Test Scan',
            'statut' => StatutProgrammeEnum::ANALYSE_EN_ATTENTE->value,
            'version' => 1,
        ]);

        $analyzerMock = $this->createMock(\App\Modules\Pedagogie\Services\ProgramDocumentAnalyzer::class);
        $analyzerMock->method('analyserFichier')->willThrowException(
            \App\Modules\Pedagogie\Exceptions\DocumentAnalyseException::documentVideOuIllisible()
        );

        $job = new ProcessProgrammeDocumentJob($programme->id, 'chemin_inexistant.pdf', 'secondaire');

        try {
            $job->handle($analyzerMock);
        } catch (\Throwable) {
            // L'exception est re-jetée par le job, comportement normal
        }

        $programme->refresh();
        $this->assertEquals(StatutProgrammeEnum::ECHEC_ANALYSE->value, $programme->statut);
        $this->assertNotNull($programme->erreur_analyse);
    }

    public function test_validation_structure_par_enseignant_passe_en_soumis(): void
    {
        $programme = Programme::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'enseignant_id' => $this->enseignantId,
            'titre' => 'Programme à valider',
            'statut' => StatutProgrammeEnum::A_VALIDER->value,
            'version' => 1,
        ]);

        $chapitre = ProgrammeChapitre::create([
            'programme_id' => $programme->id,
            'titre' => 'Chapitre 1 brut',
            'ordre' => 1,
        ]);

        app(ProgrammeServiceInterface::class)->validerParEnseignant($programme->id, [
            [
                'id' => $chapitre->id,
                'titre' => 'Chapitre 1 : Arithmétique corrigée',
                'volume_horaire_prevu' => 6.0,
            ],
        ]);

        $programme->refresh();
        $chapitre->refresh();

        $this->assertEquals(StatutProgrammeEnum::SOUMIS->value, $programme->statut);
        $this->assertEquals('Chapitre 1 : Arithmétique corrigée', $chapitre->titre);
        $this->assertEquals(6.0, $chapitre->volume_horaire_prevu);
    }

    public function test_creation_nouvelle_version_preserve_programme_original_et_progressions(): void
    {
        $programmeV1 = Programme::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'titre' => 'Programme Officiel V1',
            'statut' => 'valide',
            'version' => 1,
        ]);

        $chapitre = ProgrammeChapitre::create([
            'programme_id' => $programmeV1->id,
            'titre' => 'Chapitre V1',
            'ordre' => 1,
        ]);

        $lecon = ProgrammeLecon::create([
            'programme_chapitre_id' => $chapitre->id,
            'titre' => 'Leçon V1',
            'ordre' => 1,
            'volume_horaire_prevu' => 2.0,
            'statut' => 'terminee',
        ]);

        /** @var Programme $programmeV2 */
        $programmeV2 = app(ProgrammeServiceInterface::class)->creerNouvelleVersion($programmeV1->id);

        $this->assertEquals(2, $programmeV2->version);
        $this->assertEquals($programmeV1->id, $programmeV2->programme_parent_id);
        $this->assertEquals(StatutProgrammeEnum::BROUILLON->value, $programmeV2->statut);

        // Vérifier que V1 est toujours intact
        $programmeV1->refresh();
        $this->assertEquals(1, $programmeV1->version);
        $this->assertEquals('valide', $programmeV1->statut);
        $this->assertCount(1, $programmeV1->chapitres);

        // Vérifier que V2 a ses propres chapitres dupliqués
        $this->assertCount(1, $programmeV2->chapitres);
        $this->assertNotEquals($chapitre->id, $programmeV2->chapitres()->first()->id);
    }

    public function test_saisie_progression_multi_etats_et_devoirs_attaches(): void
    {
        $programme = Programme::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'source' => 'enseignant',
            'enseignant_id' => $this->enseignantId,
            'titre' => 'Prog Maths',
            'statut' => 'valide',
        ]);

        $offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'programme_id' => $programme->id,
            'actif' => true,
        ]);
        AffectationPedagogique::create([
            'enseignant_id' => $this->enseignantId,
            'classe_id' => 1,
            'matiere_id' => $this->matiere->id,
            'offre_pedagogique_id' => $offre->id,
            'annee_scolaire_id' => $this->anneeId,
            'type_affectation' => 'matiere',
            'actif' => true,
        ]);

        $chapitre = ProgrammeChapitre::create([
            'programme_id' => $programme->id,
            'titre' => 'Chapitre 1',
            'ordre' => 1,
            'statut' => 'en_cours',
        ]);

        $lecon = ProgrammeLecon::create([
            'programme_chapitre_id' => $chapitre->id,
            'titre' => 'Notion 1',
            'ordre' => 1,
            'volume_horaire_prevu' => 2.0,
            'statut' => 'a_faire',
        ]);

        $creneau = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00']);

        $edt = EmploiDuTemps::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'enseignant_id' => $this->enseignantId,
            'salle_id' => 1,
            'creneau_id' => $creneau->id,
            'actif' => true,
        ]);

        $seance = Seance::create([
            'emploi_du_temps_id' => $edt->id,
            'date_seance' => '2026-09-15',
            'enseignant_id' => $this->enseignantId,
            'statut' => 'dispensee',
            'progression_renseignee' => false,
        ]);

        /** @var ProgressionServiceInterface $progressionService */
        $progressionService = app(ProgressionServiceInterface::class);

        $prog = $progressionService->saisirProgression(
            seanceId: $seance->id,
            chapitreId: $chapitre->id,
            contenu: 'Introduction et premiers théorèmes',
            devoirs: 'Exercice 1 page 10',
            chapitreTermine: false,
            leconId: $lecon->id,
            statutElement: 'commencee',
            dureeMinutes: 55,
            devoirData: [
                'titre' => 'Devoir d\'application n°1',
                'description' => 'Faire les exercices 1 et 2',
                'type_devoir' => 'exercice',
                'date_limite' => '2026-09-22',
            ]
        );

        $this->assertDatabaseHas('progressions', [
            'id' => $prog->id,
            'seance_id' => $seance->id,
            'statut_element' => 'commencee',
            'duree_minutes' => 55,
            'chapitre_termine' => false,
        ]);

        $this->assertDatabaseHas('devoirs', [
            'seance_id' => $seance->id,
            'titre' => 'Devoir d\'application n°1',
            'type_devoir' => 'exercice',
        ]);
        $this->assertEquals('2026-09-22', Devoir::first()->date_limite?->format('Y-m-d'));

        $lecon->refresh();
        $this->assertEquals('en_cours', $lecon->statut);
    }

    public function test_refuse_la_saisie_si_enseignant_non_affecte_a_l_offre_de_la_seance(): void
    {
        $programme = Programme::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'source' => 'enseignant',
            'titre' => 'Programme non affecté',
            'statut' => 'valide',
        ]);
        $offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'programme_id' => $programme->id,
            'actif' => true,
        ]);
        $chapitre = ProgrammeChapitre::create(['programme_id' => $programme->id, 'titre' => 'Chapitre protégé', 'ordre' => 1]);
        $creneau = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00']);
        $edt = EmploiDuTemps::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'enseignant_id' => $this->enseignantId,
            'salle_id' => 1,
            'creneau_id' => $creneau->id,
            'actif' => true,
        ]);
        $seance = Seance::create([
            'emploi_du_temps_id' => $edt->id,
            'date_seance' => '2026-09-15',
            'enseignant_id' => $this->enseignantId,
            'statut' => 'dispensee',
        ]);

        try {
            app(ProgressionServiceInterface::class)->saisirProgression($seance->id, $chapitre->id, 'Contenu', chapitreTermine: true);
            $this->fail('La saisie aurait dû être refusée sans affectation à l’offre.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame("Vous n'êtes pas affecté à cette offre pédagogique.", $exception->getMessage());
        }

        $this->assertDatabaseMissing('progressions', ['seance_id' => $seance->id]);
        $this->assertDatabaseHas('seances', ['id' => $seance->id, 'progression_renseignee' => false]);
    }

    public function test_calcul_progression_strictement_isole_entre_deux_classes_meme_cours(): void
    {
        $classeA = 101;
        $classeB = 102;

        $programme = Programme::create([
            'matiere_id' => $this->matiere->id,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'titre' => 'Programme Maths Général',
            'statut' => 'valide',
        ]);

        $chapitre = ProgrammeChapitre::create([
            'programme_id' => $programme->id,
            'titre' => 'Chapitre Général',
            'ordre' => 1,
        ]);

        // 4 leçons de 2h chacune = 8h au total
        $lecon1 = ProgrammeLecon::create(['programme_chapitre_id' => $chapitre->id, 'titre' => 'L1', 'ordre' => 1, 'volume_horaire_prevu' => 2.0]);
        $lecon2 = ProgrammeLecon::create(['programme_chapitre_id' => $chapitre->id, 'titre' => 'L2', 'ordre' => 2, 'volume_horaire_prevu' => 2.0]);
        $lecon3 = ProgrammeLecon::create(['programme_chapitre_id' => $chapitre->id, 'titre' => 'L3', 'ordre' => 3, 'volume_horaire_prevu' => 2.0]);
        $lecon4 = ProgrammeLecon::create(['programme_chapitre_id' => $chapitre->id, 'titre' => 'L4', 'ordre' => 4, 'volume_horaire_prevu' => 2.0]);

        $creneauA = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '10:00']);
        $creneauB = CreneauHoraire::create(['jour_semaine' => 2, 'heure_debut' => '10:00', 'heure_fin' => '12:00']);

        $edtA = EmploiDuTemps::create(['matiere_id' => $this->matiere->id, 'classe_id' => $classeA, 'annee_scolaire_id' => $this->anneeId, 'enseignant_id' => $this->enseignantId, 'salle_id' => 1, 'creneau_id' => $creneauA->id, 'actif' => true]);
        $edtB = EmploiDuTemps::create(['matiere_id' => $this->matiere->id, 'classe_id' => $classeB, 'annee_scolaire_id' => $this->anneeId, 'enseignant_id' => $this->enseignantId, 'salle_id' => 1, 'creneau_id' => $creneauB->id, 'actif' => true]);

        // Pour la classe A : 3 leçons terminées (L1, L2, L3) -> 3/4 = 75%
        $sA1 = Seance::create(['emploi_du_temps_id' => $edtA->id, 'date_seance' => '2026-09-10', 'enseignant_id' => $this->enseignantId, 'statut' => 'dispensee']);
        Progression::create(['seance_id' => $sA1->id, 'chapitre_id' => $chapitre->id, 'lecon_id' => $lecon1->id, 'contenu_couvert' => 'L1', 'chapitre_termine' => true, 'statut_element' => 'terminee']);

        $sA2 = Seance::create(['emploi_du_temps_id' => $edtA->id, 'date_seance' => '2026-09-17', 'enseignant_id' => $this->enseignantId, 'statut' => 'dispensee']);
        Progression::create(['seance_id' => $sA2->id, 'chapitre_id' => $chapitre->id, 'lecon_id' => $lecon2->id, 'contenu_couvert' => 'L2', 'chapitre_termine' => true, 'statut_element' => 'terminee']);

        $sA3 = Seance::create(['emploi_du_temps_id' => $edtA->id, 'date_seance' => '2026-09-24', 'enseignant_id' => $this->enseignantId, 'statut' => 'dispensee']);
        Progression::create(['seance_id' => $sA3->id, 'chapitre_id' => $chapitre->id, 'lecon_id' => $lecon3->id, 'contenu_couvert' => 'L3', 'chapitre_termine' => true, 'statut_element' => 'terminee']);

        // Pour la classe B : seulement 1 leçon terminée (L1) -> 1/4 = 25%
        $sB1 = Seance::create(['emploi_du_temps_id' => $edtB->id, 'date_seance' => '2026-09-11', 'enseignant_id' => $this->enseignantId, 'statut' => 'dispensee']);
        Progression::create(['seance_id' => $sB1->id, 'chapitre_id' => $chapitre->id, 'lecon_id' => $lecon1->id, 'contenu_couvert' => 'L1', 'chapitre_termine' => true, 'statut_element' => 'terminee']);

        /** @var ProgressionCalculator $calculator */
        $calculator = app(ProgressionCalculator::class);

        $resultatA = $calculator->calculerProgressionClasse($this->matiere->id, $classeA, $this->anneeId);
        $resultatB = $calculator->calculerProgressionClasse($this->matiere->id, $classeB, $this->anneeId);

        $this->assertEquals(75.0, $resultatA['taux_simple']);
        $this->assertEquals(75.0, $resultatA['taux_pondere']);
        $this->assertEquals(3, $resultatA['elements_termines']);

        $this->assertEquals(25.0, $resultatB['taux_simple']);
        $this->assertEquals(25.0, $resultatB['taux_pondere']);
        $this->assertEquals(1, $resultatB['elements_termines']);
    }

    public function test_calcul_ecart_temporel_avec_jours_ouvres_et_alertes(): void
    {
        $calculator = app(ProgressionCalculator::class);

        // Date de référence simulant 50% de l'année scolaire écoulée
        $dateRef = Carbon::parse('2027-02-01');

        // Cas 1 : En retard (taux réel 20% alors que ~50% attendu)
        $ecartRetard = $calculator->calculerEcartTemporel($this->anneeId, 20.0, $dateRef);
        $this->assertEquals('en_retard', $ecartRetard['statut']);
        $this->assertLessThan(-10.0, $ecartRetard['delta']);

        // Cas 2 : En avance (taux réel 80% alors que ~50% attendu)
        $ecartAvance = $calculator->calculerEcartTemporel($this->anneeId, 80.0, $dateRef);
        $this->assertEquals('en_avance', $ecartAvance['statut']);
        $this->assertGreaterThan(10.0, $ecartAvance['delta']);

        // Cas 3 : Conforme (taux réel très proche du théorique)
        $theorique = $ecartRetard['taux_theorique'];
        $ecartConforme = $calculator->calculerEcartTemporel($this->anneeId, $theorique, $dateRef);
        $this->assertEquals('conforme', $ecartConforme['statut']);
        $this->assertEquals(0.0, $ecartConforme['delta']);
    }

    public function test_progression_monitoring_service_genere_tableaux_de_bord(): void
    {
        $mockClasse = $this->createMock(ClasseServiceInterface::class);
        $mockClasse->method('getNomClasse')->willReturn('6e A');
        $mockClasse->method('getToutesLesClasses')->willReturn([
            ['id' => 1, 'nom' => '6e A'],
        ]);
        $this->app->instance(ClasseServiceInterface::class, $mockClasse);

        $offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'classe_id' => 1,
            'niveau_id' => 1,
            'annee_scolaire_id' => $this->anneeId,
            'actif' => true,
        ]);

        AffectationPedagogique::create([
            'enseignant_id' => $this->enseignantId,
            'classe_id' => 1,
            'matiere_id' => $this->matiere->id,
            'offre_pedagogique_id' => $offre->id,
            'annee_scolaire_id' => $this->anneeId,
            'type_affectation' => 'matiere',
            'actif' => true,
        ]);

        $monitoring = app(ProgressionMonitoringService::class);

        $enseignantDash = $monitoring->getTableauDeBordEnseignant($this->enseignantId, $this->anneeId);
        $this->assertIsArray($enseignantDash);
        $this->assertCount(1, $enseignantDash);
        $this->assertEquals('Mathématiques Modernes', $enseignantDash[0]['matiere_nom']);

        $directionDash = $monitoring->getSupervisionDirection($this->anneeId);
        $this->assertIsArray($directionDash);
        $this->assertArrayHasKey('alertes_retard', $directionDash);
        $this->assertArrayHasKey('syntheses', $directionDash);
    }
}
