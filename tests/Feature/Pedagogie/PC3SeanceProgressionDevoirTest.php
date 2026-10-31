<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\DocumentExtractor;
use App\Modules\Pedagogie\Contracts\ProgramDocumentAnalyzer;
use App\Modules\Pedagogie\Jobs\AnalyseProgramme;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\Devoir;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Pedagogie\Services\ProgramImportService;
use App\Modules\Pedagogie\Services\ProgramStructureService;
use App\Modules\Pedagogie\Services\ProgressionCalculator;
use App\Modules\Pedagogie\Services\SessionProgressService;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PC3SeanceProgressionDevoirTest extends TestCase
{
    use RefreshDatabase;

    private User $userEnseignant;

    private Enseignant $enseignant;

    private AnneeScolaire $annee;

    private Classe $classe;

    private OffrePedagogique $offre;

    private AffectationPedagogique $affectation;

    private Matiere $matiere;

    private EmploiDuTemps $emploiDuTemps;

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

        $cycle = Cycle::create(['code' => 'COL-PC3', 'nom' => 'Collège', 'ordre' => 2]);
        $niveau = Niveau::create(['cycle_id' => $cycle->id, 'code' => '6EME-PC3', 'nom' => '6ème', 'ordre' => 1]);

        $this->classe = Classe::create([
            'nom' => '6ème A',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 35,
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
            'matricule' => 'E-PC3-01',
            'nom' => 'Kamga',
            'prenom' => 'Samuel',
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
            'nom' => 'Histoire-Géographie',
            'code' => 'HG-PC3',
            'nature' => 'matiere',
            'coefficient' => 3,
            'coefficient_defaut' => 3,
            'actif' => true,
        ]);

        $this->offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'niveau_id' => $niveau->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'coefficient_matiere' => 3,
            'actif' => true,
        ]);

        $this->affectation = AffectationPedagogique::create([
            'enseignant_id' => $this->enseignant->id,
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
            'offre_pedagogique_id' => $this->offre->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'matiere',
            'actif' => true,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ]);

        $creneau = CreneauHoraire::create([
            'jour_semaine' => 1,
            'heure_debut' => '08:00:00',
            'heure_fin' => '10:00:00',
            'ordre' => 1,
        ]);

        $this->emploiDuTemps = EmploiDuTemps::create([
            'creneau_id' => $creneau->id,
            'salle_id' => 1,
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
            'enseignant_id' => $this->enseignant->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'publie',
            'actif' => true,
        ]);

        $this->actingAs($this->userEnseignant);
    }

    private function creerDocumentWord(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'programme').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Unité 1 : La Méditerranée antique</w:t></w:r></w:p><w:p><w:r><w:t>Leçon 1 : Les cités grecques</w:t></w:r></w:p><w:p><w:r><w:t>Objectif : Découvrir Athènes au Ve siècle</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'programme_hg.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    private function importerEtValiderProgramme(): Programme
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

        $structure = app(ProgramStructureService::class)->proposition($programme);

        return app(ProgramStructureService::class)->enregistrer($programme, $structure, true)->fresh();
    }

    private function creerSeanceDispensee(): Seance
    {
        return Seance::create([
            'emploi_du_temps_id' => $this->emploiDuTemps->id,
            'enseignant_id' => $this->enseignant->id,
            'date_seance' => '2026-10-05',
            'statut' => 'dispensee',
            'progression_renseignee' => false,
        ]);
    }

    public function test_pc3_1_consommation_programme_valide_uniquement(): void
    {
        // 1. Cas programme en BROUILLON : tentative de progression doit être rejetée (422)
        $fichier = $this->creerDocumentWord();
        $programmeBrouillon = app(ProgramImportService::class)->importer(
            $this->annee->id,
            $this->classe->id,
            $this->offre->id,
            $fichier
        );

        (new AnalyseProgramme($programmeBrouillon->id))->handle(
            app(DocumentExtractor::class),
            app(ProgramDocumentAnalyzer::class),
            app(DocumentServiceContract::class)
        );

        // Le programme est à ce stade en analyse terminée mais NON validé (statut = brouillon)
        $this->assertEquals('brouillon', $programmeBrouillon->fresh()->statut);
        $parentsBrouillon = $programmeBrouillon->chapitres()->pluck('parent_id')->filter()->all();
        $chapitre = $programmeBrouillon->chapitres()->whereNotIn('id', $parentsBrouillon)->firstOrFail();

        $seance = $this->creerSeanceDispensee();
        $progressService = app(SessionProgressService::class);

        try {
            $progressService->enregistrer([
                'seance_id' => $seance->id,
                'chapitre_id' => $chapitre->id,
                'etat_avancement' => 'en_cours',
                'contenu_couvert' => 'Introduction aux cités grecques',
                'devoir_donne' => false,
            ]);
            $this->fail('Une séance ne doit pas pouvoir consommer un programme non validé.');
        } catch (HttpException $e) {
            $this->assertEquals(422, $e->getStatusCode());
        }

        // 2. Validation du programme -> la séance peut maintenant être enregistrée
        $structure = app(ProgramStructureService::class)->proposition($programmeBrouillon);
        app(ProgramStructureService::class)->enregistrer($programmeBrouillon, $structure, true);

        $programmeValide = $programmeBrouillon->fresh();
        $this->assertEquals('valide', $programmeValide->statut);

        $parentsValide = $programmeValide->chapitres()->pluck('parent_id')->filter()->all();
        $chapitreValide = $programmeValide->chapitres()->whereNotIn('id', $parentsValide)->firstOrFail();

        $progression = $progressService->enregistrer([
            'seance_id' => $seance->id,
            'chapitre_id' => $chapitreValide->id,
            'etat_avancement' => 'en_cours',
            'contenu_couvert' => 'Introduction aux cités grecques',
            'devoir_donne' => false,
        ]);

        $this->assertInstanceOf(Progression::class, $progression);
        $this->assertTrue($seance->fresh()->progression_renseignee);
    }

    public function test_pc3_2_seance_progression_reelle_et_calcul_pourcentage_sans_double_comptage(): void
    {
        $programme = $this->importerEtValiderProgramme();

        // Création contrôlée d'une structure de 4 leçons pour vérifier le calcul de %
        $chapitres = [
            ['titre' => 'Chapitre 1', 'type' => 'chapitre', 'volume_horaire_prevu' => 4.0, 'parent_index' => null],
            ['titre' => 'Leçon 1.1', 'type' => 'lecon', 'volume_horaire_prevu' => 2.0, 'parent_index' => 0],
            ['titre' => 'Leçon 1.2', 'type' => 'lecon', 'volume_horaire_prevu' => 2.0, 'parent_index' => 0],
            ['titre' => 'Chapitre 2', 'type' => 'chapitre', 'volume_horaire_prevu' => 6.0, 'parent_index' => null],
            ['titre' => 'Leçon 2.1', 'type' => 'lecon', 'volume_horaire_prevu' => 3.0, 'parent_index' => 3],
            ['titre' => 'Leçon 2.2', 'type' => 'lecon', 'volume_horaire_prevu' => 3.0, 'parent_index' => 3],
        ];

        // Remplacement de la structure
        $programme->update(['statut' => 'brouillon', 'analyse_statut' => 'a_valider']);
        app(ProgramStructureService::class)->enregistrer($programme, $chapitres, true);
        $programme->refresh();

        $lecons = $programme->chapitres()->whereNotNull('parent_id')->get();
        $this->assertCount(4, $lecons); // 4 leçons : 2h + 2h + 3h + 3h = 10h

        $calculator = app(ProgressionCalculator::class);

        // 1. Initial : 0%
        $mesure0 = $calculator->pourChapitres($programme->chapitres, $this->classe->id, $this->annee->id);
        $this->assertEquals(0.0, $mesure0['pourcentage']);
        $this->assertEquals(0, $mesure0['termines']);

        // 2. Séance 1 : Réalisation de la Leçon 1.1 (2h terminée)
        $seance1 = $this->creerSeanceDispensee();
        app(SessionProgressService::class)->enregistrer([
            'seance_id' => $seance1->id,
            'chapitre_id' => $lecons[0]->id,
            'etat_avancement' => 'terminee',
            'contenu_couvert' => 'Leçon 1.1 terminée',
            'devoir_donne' => false,
        ]);

        $mesure1 = $calculator->pourChapitres($programme->chapitres, $this->classe->id, $this->annee->id);
        // 2h réalisées sur 10h prévues = 20.0%
        $this->assertEquals(20.0, $mesure1['pourcentage']);
        $this->assertEquals(1, $mesure1['termines']);

        // 3. INVARIANT ANTI-DOUBLE COMPTAGE :
        // Si une autre séance mentionne à nouveau la Leçon 1.1 comme terminée
        $seanceDoublon = Seance::create([
            'emploi_du_temps_id' => $this->emploiDuTemps->id,
            'enseignant_id' => $this->enseignant->id,
            'date_seance' => '2026-10-06',
            'statut' => 'dispensee',
            'progression_renseignee' => false,
        ]);
        Progression::create([
            'seance_id' => $seanceDoublon->id,
            'chapitre_id' => $lecons[0]->id,
            'etat_avancement' => 'terminee',
            'chapitre_termine' => true,
            'contenu_couvert' => 'Répétition leçon 1.1',
            'saisi_par' => $this->userEnseignant->id,
        ]);

        // Le calcul doit TOUJOURS donner 20.0% (pluck->unique), JAMAIS 40.0%
        $mesureDoublon = $calculator->pourChapitres($programme->chapitres, $this->classe->id, $this->annee->id);
        $this->assertEquals(20.0, $mesureDoublon['pourcentage']);
        $this->assertEquals(1, $mesureDoublon['termines']);

        // 4. Séance 2 : Réalisation de la Leçon 2.1 (3h terminée)
        $seance2 = Seance::create([
            'emploi_du_temps_id' => $this->emploiDuTemps->id,
            'enseignant_id' => $this->enseignant->id,
            'date_seance' => '2026-10-07',
            'statut' => 'dispensee',
            'progression_renseignee' => false,
        ]);
        app(SessionProgressService::class)->enregistrer([
            'seance_id' => $seance2->id,
            'chapitre_id' => $lecons[2]->id,
            'etat_avancement' => 'terminee',
            'contenu_couvert' => 'Leçon 2.1 traitée',
            'devoir_donne' => false,
        ]);

        // (2h + 3h) = 5h sur 10h = 50.0%
        $mesure2 = $calculator->pourChapitres($programme->chapitres, $this->classe->id, $this->annee->id);
        $this->assertEquals(50.0, $mesure2['pourcentage']);
        $this->assertEquals(2, $mesure2['termines']);
    }

    public function test_pc3_3_attachement_devoir_a_la_seance_et_progression(): void
    {
        $programme = $this->importerEtValiderProgramme();
        $parents = $programme->chapitres()->pluck('parent_id')->filter()->all();
        $chapitre = $programme->chapitres()->whereNotIn('id', $parents)->firstOrFail();
        $seance = $this->creerSeanceDispensee();

        // 1. Rejet si date de remise antérieure à la séance
        try {
            app(SessionProgressService::class)->enregistrer([
                'seance_id' => $seance->id,
                'chapitre_id' => $chapitre->id,
                'etat_avancement' => 'en_cours',
                'contenu_couvert' => 'Cours du jour',
                'devoir_donne' => true,
                'type_devoir' => 'exercice',
                'consigne' => 'Exercices 1 et 2',
                'description_devoir' => 'Page 42 du manuel',
                'date_remise' => '2026-10-04', // Antérieur au 2026-10-05 de la séance
            ]);
            $this->fail('Une date de remise antérieure à la séance aurait dû lever une ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('date_remise', $e->errors());
        }

        // 2. Enregistrement valide du devoir rattaché à la progression
        $progression = app(SessionProgressService::class)->enregistrer([
            'seance_id' => $seance->id,
            'chapitre_id' => $chapitre->id,
            'etat_avancement' => 'en_cours',
            'contenu_couvert' => 'Cours du jour',
            'devoir_donne' => true,
            'type_devoir' => 'exercice',
            'consigne' => 'Exercices 1 et 2',
            'description_devoir' => 'Page 42 du manuel',
            'date_remise' => '2026-10-12',
        ]);

        $this->assertDatabaseHas('devoirs_pedagogiques', [
            'progression_id' => $progression->id,
            'seance_id' => $seance->id,
            'classe_id' => $this->classe->id,
            'enseignant_id' => $this->enseignant->id,
            'type' => 'exercice',
            'consigne' => 'Exercices 1 et 2',
            'date_remise' => '2026-10-12',
        ]);

        // 3. Empêcher une seconde progression sur la même séance
        try {
            app(SessionProgressService::class)->enregistrer([
                'seance_id' => $seance->id,
                'chapitre_id' => $chapitre->id,
                'etat_avancement' => 'terminee',
                'contenu_couvert' => 'Deuxième progression tentée',
                'devoir_donne' => false,
            ]);
            $this->fail('Une double progression sur la même séance doit être refusée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('seance_id', $e->errors());
        }
    }

    public function test_pc3_4_coherence_devoir_vs_devoir_pedagogique(): void
    {
        $seance = $this->creerSeanceDispensee();

        // Modèle Devoir (table devoirs) : lié directement à la séance
        $devoir = Devoir::create([
            'seance_id' => $seance->id,
            'titre' => 'Devoir Maison N°1',
            'description' => 'Commentaire de document historique',
            'type_devoir' => 'exercice',
            'date_limite' => '2026-10-15',
            'donne_par' => $this->userEnseignant->id,
        ]);

        $this->assertDatabaseHas('devoirs', [
            'id' => $devoir->id,
            'seance_id' => $seance->id,
            'titre' => 'Devoir Maison N°1',
        ]);

        // La séance accède bien à son devoir via relation
        $this->assertInstanceOf(Devoir::class, $seance->devoirs()->first());
        $this->assertEquals($devoir->id, $seance->devoirs()->first()->id);
    }
}
