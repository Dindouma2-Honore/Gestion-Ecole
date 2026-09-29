<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use App\Modules\RH\Contracts\EnseignantServiceInterface;
use App\Modules\Scolarite\Contracts\ClasseServiceInterface;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Niveau;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProgrammeEnseignantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Directeur']);
        Role::firstOrCreate(['name' => 'Fondateur']);
        Role::firstOrCreate(['name' => 'Enseignant']);

        // Create active school year
        AnneeScolaire::firstOrCreate(
            ['id' => 1],
            ['libelle' => '2026-2027', 'statut' => 'active', 'date_debut' => '2026-09-01', 'date_fin' => '2027-06-30']
        );

        // Create niveaux
        foreach ([1 => 'Maternelle', 2 => 'Primaire', 3 => 'Secondaire'] as $id => $nom) {
            Niveau::firstOrCreate(['id' => $id], ['nom' => $nom, 'code' => 'N' . $id, 'ordre' => $id]);
        }
    }

    public function test_soumettre_programme_fails_if_enseignant_not_assigned(): void
    {
        $mockEnseignantService = Mockery::mock(EnseignantServiceInterface::class);
        $mockEnseignantService->shouldReceive('estAffecteA')
            ->with(99, 1, 5)
            ->andReturn(false);

        $this->app->instance(EnseignantServiceInterface::class, $mockEnseignantService);

        $service = app(ProgrammeServiceInterface::class);
        $fichier = UploadedFile::fake()->create('syllabus.pdf', 100);

        $this->expectException(InvalidArgumentException::class);
        $service->soumettreProgramme(
            enseignantId: 99,
            matiereId: 1,
            classeId: 5,
            anneeScolaireId: 1,
            fichierSource: $fichier,
            chapitres: [['titre' => 'Chapitre 1', 'ordre' => 1]]
        );
    }

    public function test_soumettre_programme_deduces_niveau_and_salle_and_creates_records(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);

        $matiere = Matiere::create(['nom' => 'Physique', 'code' => 'PHYS-1', 'coefficient' => 2, 'actif' => true]);

        $mockEnseignantService = Mockery::mock(EnseignantServiceInterface::class);
        $mockEnseignantService->shouldReceive('estAffecteA')
            ->with(10, $matiere->id, 5)
            ->andReturn(true);
        $this->app->instance(EnseignantServiceInterface::class, $mockEnseignantService);

        $mockClasseService = Mockery::mock(ClasseServiceInterface::class);
        $mockClasseService->shouldReceive('getNiveauId')
            ->with(5)
            ->andReturn(3);
        $this->app->instance(ClasseServiceInterface::class, $mockClasseService);

        $creneau = \App\Modules\Pedagogie\Models\CreneauHoraire::create([
            'jour_semaine' => 1,
            'heure_debut' => '08:00:00',
            'heure_fin' => '09:00:00',
        ]);

        EmploiDuTemps::create([
            'enseignant_id' => 10,
            'matiere_id' => $matiere->id,
            'classe_id' => 5,
            'salle_id' => 42,
            'creneau_id' => $creneau->id,
            'annee_scolaire_id' => 1,
            'actif' => true,
        ]);

        $directeur = User::factory()->create(['niveau_id' => 3, 'statut' => 'actif']);
        $directeur->assignRole('Directeur');

        $service = app(ProgrammeServiceInterface::class);
        $fichier = UploadedFile::fake()->create('programme_physique.pdf', 150);

        $programme = $service->soumettreProgramme(
            enseignantId: 10,
            matiereId: $matiere->id,
            classeId: 5,
            anneeScolaireId: 1,
            fichierSource: $fichier,
            chapitres: [
                ['titre' => 'Cinématique', 'ordre' => 1, 'objectifs_pedagogiques' => 'Mouvement rectiligne'],
                ['titre' => 'Dynamique', 'ordre' => 2, 'objectifs_pedagogiques' => 'Lois de Newton'],
            ]
        );

        $this->assertNotNull($programme);
        $this->assertSame('enseignant', $programme->source);
        $this->assertSame('soumis', $programme->statut);
        $this->assertSame(3, $programme->niveau_id);
        $this->assertSame(42, $programme->salle_id);
        $this->assertSame(10, $programme->enseignant_id);

        $chapitres = ProgrammeChapitre::where('programme_id', $programme->id)->get();
        $this->assertCount(2, $chapitres);
        $this->assertSame('Cinématique', $chapitres->first()->titre);
    }

    public function test_valider_programme_switches_get_chapitres_prevus_to_teacher_program(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);

        $matiere = Matiere::create(['nom' => 'Chimie', 'code' => 'CHIM-1', 'coefficient' => 2, 'actif' => true]);

        $mockEnseignantService = Mockery::mock(EnseignantServiceInterface::class);
        $mockEnseignantService->shouldReceive('estAffecteA')->andReturn(true);
        $this->app->instance(EnseignantServiceInterface::class, $mockEnseignantService);

        $mockClasseService = Mockery::mock(ClasseServiceInterface::class);
        $mockClasseService->shouldReceive('getNiveauId')->andReturn(2);
        $this->app->instance(ClasseServiceInterface::class, $mockClasseService);

        // Create official program
        $progOfficiel = Programme::create([
            'matiere_id' => $matiere->id,
            'niveau_id' => 2,
            'annee_scolaire_id' => 1,
            'source' => 'officiel',
            'titre' => 'Programme Officiel Chimie',
            'statut' => 'valide',
        ]);
        ProgrammeChapitre::create(['programme_id' => $progOfficiel->id, 'titre' => 'Chapitre Officiel 1', 'ordre' => 1]);

        $service = app(ProgrammeServiceInterface::class);

        // Before teacher program: getChapitresPrevus returns official program
        $chapitresBefore = $service->getChapitresPrevus($matiere->id, 4, 1);
        $this->assertCount(1, $chapitresBefore);
        $this->assertSame('Chapitre Officiel 1', $chapitresBefore->first()->titre);

        // Submit teacher program
        $fichier = UploadedFile::fake()->create('chimie.pdf', 100);
        $progEnseignant = $service->soumettreProgramme(
            enseignantId: 7,
            matiereId: $matiere->id,
            classeId: 4,
            anneeScolaireId: 1,
            fichierSource: $fichier,
            chapitres: [
                ['titre' => 'Chapitre Enseignant 1', 'ordre' => 1],
                ['titre' => 'Chapitre Enseignant 2', 'ordre' => 2],
            ]
        );

        // Still returns official program while statut is 'soumis'
        $chapitresPending = $service->getChapitresPrevus($matiere->id, 4, 1);
        $this->assertSame('Chapitre Officiel 1', $chapitresPending->first()->titre);

        // Validate teacher program
        $service->validerProgramme($progEnseignant->id, 1);

        // Now returns teacher program chapters
        $chapitresAfter = $service->getChapitresPrevus($matiere->id, 4, 1);
        $this->assertCount(2, $chapitresAfter);
        $this->assertSame('Chapitre Enseignant 1', $chapitresAfter->first()->titre);
    }

    public function test_rejeter_programme_requires_motif_and_falls_back_to_official_program(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);

        $matiere = Matiere::create(['nom' => 'Histoire', 'code' => 'HIST-1', 'coefficient' => 1, 'actif' => true]);

        $mockEnseignantService = Mockery::mock(EnseignantServiceInterface::class);
        $mockEnseignantService->shouldReceive('estAffecteA')->andReturn(true);
        $this->app->instance(EnseignantServiceInterface::class, $mockEnseignantService);

        $mockClasseService = Mockery::mock(ClasseServiceInterface::class);
        $mockClasseService->shouldReceive('getNiveauId')->andReturn(1);
        $this->app->instance(ClasseServiceInterface::class, $mockClasseService);

        $progOfficiel = Programme::create([
            'matiere_id' => $matiere->id,
            'niveau_id' => 1,
            'annee_scolaire_id' => 1,
            'source' => 'officiel',
            'titre' => 'Programme Officiel Histoire',
            'statut' => 'valide',
        ]);
        ProgrammeChapitre::create(['programme_id' => $progOfficiel->id, 'titre' => 'Histoire Chap 1', 'ordre' => 1]);

        $service = app(ProgrammeServiceInterface::class);
        $fichier = UploadedFile::fake()->create('histoire.pdf', 100);

        $progEnseignant = $service->soumettreProgramme(
            enseignantId: 8,
            matiereId: $matiere->id,
            classeId: 2,
            anneeScolaireId: 1,
            fichierSource: $fichier,
            chapitres: [['titre' => 'Mon Chapitre Proposé', 'ordre' => 1]]
        );

        // Empty motif throws exception
        try {
            $service->rejeterProgramme($progEnseignant->id, '   ');
            $this->fail('Un motif de rejet vide doit lever une exception.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        // Rejection with motif
        $service->rejeterProgramme($progEnseignant->id, 'Incompatible avec le programme officiel');

        $progEnseignant = Programme::find($progEnseignant->id);
        $this->assertSame('rejete', $progEnseignant->statut);
        $this->assertSame('Incompatible avec le programme officiel', $progEnseignant->motif_rejet);

        // Fallback to official program
        $chapitres = $service->getChapitresPrevus($matiere->id, 2, 1);
        $this->assertCount(1, $chapitres);
        $this->assertSame('Histoire Chap 1', $chapitres->first()->titre);
    }

    public function test_importer_chapitres_depuis_excel_parses_csv_rows(): void
    {
        $csvContent = "titre,ordre,objectifs,periode\nIntroduction aux fractions,1,Comprendre les tiers,1\nMultiplication,2,Multiplication simple,1\n";
        $tmpPath = sys_get_temp_dir() . '/test_import.csv';
        file_put_contents($tmpPath, $csvContent);

        $fichierExcel = new UploadedFile($tmpPath, 'test_import.csv', 'text/csv', null, true);

        $service = app(ProgrammeServiceInterface::class);
        $chapitres = $service->importerChapitresDepuisExcel($fichierExcel);

        $this->assertCount(2, $chapitres);
        $this->assertSame('Introduction aux fractions', $chapitres[0]['titre']);
        $this->assertSame('Multiplication', $chapitres[1]['titre']);

        @unlink($tmpPath);
    }
}
