<?php

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\DocumentExtractor;
use App\Modules\Pedagogie\Contracts\ProgramDocumentAnalyzer;
use App\Modules\Pedagogie\Filament\Resources\ProgrammeResource\Pages\ListProgrammes;
use App\Modules\Pedagogie\Filament\Resources\ProgressionResource\Pages\ListProgressions;
use App\Modules\Pedagogie\Filament\Resources\SuiviProgressionResource\Pages\ListSuiviProgressions;
use App\Modules\Pedagogie\Jobs\AnalyseProgramme;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Progression;
use App\Modules\Pedagogie\Services\ProgramImportService;
use App\Modules\Pedagogie\Services\ProgrammeAccessService;
use App\Modules\Pedagogie\Services\ProgrammeStructureAnalyzer;
use App\Modules\Pedagogie\Services\ProgramStructureService;
use App\Modules\Pedagogie\Services\ProgressionCalculator;
use App\Modules\Pedagogie\Services\ProgressionMonitoringService;
use App\Modules\Pedagogie\Services\SessionProgressService;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Models\ConfigEtablissement;
use App\Modules\Socle\Models\Document;
use App\Modules\Socle\Models\JourFerie;
use App\Modules\Socle\Models\Periode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProgrammesProgressionWorkflowTest extends TestCase
{
    use ProgrammesProgressionFixture;
    use RefreshDatabase;

    public function test_principal_access_uses_rh_identity_even_without_single_level(): void
    {
        $offers = app(ProgrammeAccessService::class)->offres($this->year->id);
        $this->assertCount(1, $offers);
        $this->assertSame($this->teacher->id, array_values($offers)[0]['enseignant_id']);
        $this->assignment->update(['date_fin' => '2026-10-01']);
        $this->assertSame([], app(ProgrammeAccessService::class)->offres($this->year->id));
    }

    public function test_specialist_only_sees_assigned_subjects(): void
    {
        $this->assignment->update(['type' => 'assistant']);
        $other = Matiere::create(['nom' => 'Anglais', 'code' => 'EN-T', 'coefficient' => 1, 'actif' => true]);
        $this->assignment->matieresSpecialisees()->sync([$other->id]);
        $this->assertSame([], app(ProgrammeAccessService::class)->offres($this->year->id));
        $this->assignment->matieresSpecialisees()->sync([$this->offer->matiere_id]);
        $this->assertCount(1, app(ProgrammeAccessService::class)->offres($this->year->id));
    }

    public function test_other_offer_is_refused(): void
    {
        $this->expectException(HttpException::class);
        app(ProgramImportService::class)->importer($this->year->id, 99999, $this->offer->id, $this->file());
    }

    public function test_user_without_rh_has_no_offers(): void
    {
        $u = User::factory()->create(['statut' => 'actif']);
        $u->assignRole('Enseignant');
        $this->actingAs($u);
        $this->assertSame([], app(ProgrammeAccessService::class)->offres($this->year->id));
    }

    public function test_docx_import_is_private_and_asynchronous(): void
    {
        $p = $this->importer();
        $this->assertSame('analyse_en_attente', $p->analyse_statut);
        $this->assertSame(0, $p->chapitres()->count());
        Queue::assertPushed(AnalyseProgramme::class);
        $this->assertNotNull($p->document_source_id);
        $p = $this->analyser($p);
        $this->assertSame('a_valider', $p->analyse_statut);
        $this->assertSame('brouillon', $p->statut);
        $this->assertCount(3, $p->chapitres);
    }

    public function test_invalid_file_is_rejected_before_storage(): void
    {
        $this->expectException(ValidationException::class);
        app(ProgramImportService::class)->importer($this->year->id, $this->class->id, $this->offer->id, UploadedFile::fake()->createWithContent('fake.pdf', 'this is not a PDF'));
    }

    public function test_analysis_failure_retains_original_and_can_retry(): void
    {
        $p = $this->importer();
        $extractor = \Mockery::mock(DocumentExtractor::class);
        $extractor->shouldReceive('extraire')->andThrow(new \RuntimeException('No text / OCR required'));
        (new AnalyseProgramme($p->id))->handle($extractor, app(ProgramDocumentAnalyzer::class), app(DocumentServiceContract::class));
        $this->assertSame('echec_analyse', $p->fresh()->analyse_statut);
        $this->assertFileExists(app(DocumentServiceContract::class)->sourceInterne($p->document_source_id)['path']);
        app(ProgramImportService::class)->relancer($p->fresh());
        $this->assertSame('a_valider', $this->analyser($p)->analyse_statut);
    }

    public function test_human_correction_validation_and_versions_preserve_history(): void
    {
        $p = $this->analyser($this->importer());
        $elements = app(ProgramStructureService::class)->proposition($p);
        $elements[1]['titre'] = 'Addition corrigée';
        app(ProgramStructureService::class)->enregistrer($p, $elements);
        $this->assertSame('brouillon', $p->fresh()->statut);
        $p = app(ProgramStructureService::class)->enregistrer($p, $elements, true);
        $ids = $p->chapitres()->pluck('id')->all();
        $new = $this->importer();
        $this->assertSame(2, $new->version);
        $this->assertSame($p->id, $new->origine_programme_id);
        $this->assertSame($ids, $p->chapitres()->pluck('id')->all());
        $this->expectException(HttpException::class);
        app(ProgramStructureService::class)->enregistrer($p, $elements);
    }

    public function test_multiple_sessions_only_completion_counts_and_no_homework_is_created(): void
    {
        $p = $this->valide();
        $service = app(SessionProgressService::class);
        foreach (['commencee', 'en_cours', 'terminee'] as $state) {
            $s = $this->creerSeance();
            $service->enregistrer($this->progressionData($p, $s, ['etat_avancement' => $state]));
            $this->assertSame($state === 'terminee' ? 50.0 : 0.0, app(ProgressionCalculator::class)->pourChapitres($p->chapitres, $this->class->id, $this->year->id)['pourcentage']);
        }
        $this->assertDatabaseCount('progressions', 3);
        $this->assertDatabaseCount('devoirs_pedagogiques', 0);
    }

    public function test_homework_context_and_audited_correction(): void
    {
        $p = $this->valide();
        $s = $this->creerSeance();
        $data = $this->progressionData($p, $s, ['devoir_donne' => true, 'type_devoir' => 'maison', 'consigne' => 'Exercice 4', 'date_remise' => '2026-10-06']);
        $progress = app(SessionProgressService::class)->enregistrer($data);
        $this->assertDatabaseHas('devoirs_pedagogiques', ['progression_id' => $progress->id, 'classe_id' => $this->class->id, 'offre_pedagogique_id' => $this->offer->id]);
        app(SessionProgressService::class)->enregistrer(array_replace($data, ['etat_avancement' => 'terminee']), $progress->id);
        $this->assertTrue($progress->fresh()->chapitre_termine);
        $this->assertDatabaseHas('activity_log', ['subject_type' => Progression::class, 'subject_id' => $progress->id]);
    }

    public function test_other_teacher_cannot_edit_structure(): void
    {
        $p = $this->analyser($this->importer());
        $u = User::factory()->create(['statut' => 'actif']);
        $u->assignRole('Enseignant');
        $this->actingAs($u);
        $this->expectException(HttpException::class);
        app(ProgramStructureService::class)->enregistrer($p, app(ProgramStructureService::class)->proposition($p), true);
    }

    public function test_session_must_be_realised_and_element_from_correct_programme(): void
    {
        $p = $this->valide();
        $s = $this->creerSeance();
        $s->update(['statut' => 'programmee']);
        $this->expectException(ModelNotFoundException::class);
        app(SessionProgressService::class)->enregistrer($this->progressionData($p, $s));
    }

    public function test_temporal_progress_uses_working_days_and_holidays(): void
    {
        $this->year->update(['date_debut' => '2026-10-05', 'date_fin' => '2026-10-09']);
        ConfigEtablissement::create(['nom' => 'École', 'jours_ouvrables' => [1, 2, 3, 4, 5]]);
        Periode::create(['annee_scolaire_id' => $this->year->id, 'libelle' => 'Semaine', 'type' => 'trimestre', 'date_debut' => '2026-10-05', 'date_fin' => '2026-10-09', 'ordre' => 1]);
        JourFerie::create(['affecte_cours' => true, 'libelle' => 'Repos', 'date' => '2026-10-06', 'date_debut' => '2026-10-06', 'date_fin' => '2026-10-06']);
        $this->travelTo(now()->setDate(2026, 10, 7));
        $this->assertSame(25.0, app(ProgressionMonitoringService::class)->temps($this->year->id, $this->class->niveau_id));
    }

    public function test_programme_and_progression_screens_use_existing_modals(): void
    {
        $p = $this->valide();
        Livewire::test(ListProgrammes::class)->assertSuccessful()->assertCanSeeTableRecords([$p]);
        Livewire::test(ListProgressions::class)->assertSuccessful();
        Livewire::test(ListSuiviProgressions::class)->assertSuccessful()->assertCanSeeTableRecords([$p]);
    }

    public function test_analyzer_supports_units_activities_and_more_than_sixteen_chapters(): void
    {
        $text = implode("\n", array_map(fn ($i) => 'Unité '.$i.' : Domaine', range(1, 20)))."\nActivité : Peinture\nCompétence : Créativité";
        $this->assertCount(22, app(ProgrammeStructureAnalyzer::class)->analyser($text));
    }

    public function test_pdf_import_and_extraction(): void
    {
        $pdf = Pdf::loadHTML('<h1>Chapitre 1 : Nombres</h1><h2>Lecon 1 : Addition</h2><p>Addition des nombres entiers.</p>')->output();
        $path = tempnam(sys_get_temp_dir(), 'programme-pdf');
        file_put_contents($path, $pdf);
        $p = app(ProgramImportService::class)->importer($this->year->id, $this->class->id, $this->offer->id, new UploadedFile($path, 'programme.pdf', null, null, true));
        $this->assertSame('a_valider', $this->analyser($p)->analyse_statut);
    }

    public function test_no_text_pdf_is_never_validated(): void
    {
        $pdf = Pdf::loadHTML('<html><body></body></html>')->output();
        $path = tempnam(sys_get_temp_dir(), 'empty-pdf');
        file_put_contents($path, $pdf);
        $p = app(ProgramImportService::class)->importer($this->year->id, $this->class->id, $this->offer->id, new UploadedFile($path, 'scan.pdf', null, null, true));
        $p = $this->analyser($p);
        $this->assertSame('echec_analyse', $p->analyse_statut);
        $this->assertSame('brouillon', $p->statut);
        $this->assertStringContainsString('OCR', $p->analyse_erreur);
    }

    public function test_subject_assignment_and_inactive_override(): void
    {
        $this->assignment->update(['type' => 'matiere', 'matiere_id' => $this->offer->matiere_id]);
        $this->assertCount(1, app(ProgrammeAccessService::class)->offres($this->year->id));
        OffrePedagogique::create(['matiere_id' => $this->offer->matiere_id, 'niveau_id' => $this->class->niveau_id, 'classe_id' => $this->class->id, 'annee_scolaire_id' => $this->year->id, 'coefficient_matiere' => 1, 'actif' => false]);
        $this->assertCount(0, app(ProgrammeAccessService::class)->offres($this->year->id));
    }

    public function test_analysis_job_does_not_replace_a_validated_structure(): void
    {
        $p = $this->valide();
        $ids = $p->chapitres()->pluck('id')->all();
        $this->analyser($p);
        $this->assertSame($ids, $p->chapitres()->pluck('id')->all());
        $this->assertSame('valide', $p->fresh()->statut);
    }

    public function test_versions_keep_session_references_and_separate_progress(): void
    {
        $p = $this->valide();
        $s = $this->creerSeance();
        $progress = app(SessionProgressService::class)->enregistrer($this->progressionData($p, $s, ['etat_avancement' => 'terminee']));
        $oldElement = $progress->chapitre_id;
        $new = $this->valide();
        $this->assertSame($oldElement, $progress->fresh()->chapitre_id);
        $this->assertSame(0.0, app(ProgressionCalculator::class)->pourChapitres($new->chapitres, $this->class->id, $this->year->id)['pourcentage']);
    }

    public function test_foreign_element_is_rejected(): void
    {
        $old = $this->valide();
        $new = $this->valide();
        $s = $this->creerSeance();
        $this->expectException(HttpException::class);
        app(SessionProgressService::class)->enregistrer($this->progressionData($old, $s));
    }

    public function test_correction_expires_without_deleting_history(): void
    {
        $p = $this->valide();
        $s = $this->creerSeance();
        $data = $this->progressionData($p, $s);
        $progress = app(SessionProgressService::class)->enregistrer($data);
        $this->travel(25)->hours();
        $this->expectException(HttpException::class);
        app(SessionProgressService::class)->enregistrer($data, $progress->id);
    }

    public function test_direction_only_sees_its_level_and_cannot_validate_for_teacher(): void
    {
        $p = $this->valide();
        $director = User::factory()->create(['statut' => 'actif', 'niveau_id' => $this->class->niveau_id]);
        $director->assignRole('Directeur');
        $director->givePermissionTo('module.pedagogie');
        $this->actingAs($director);
        $this->assertTrue(app(ProgrammeAccessService::class)->visibleQuery()->whereKey($p->id)->exists());
        $director->update(['niveau_id' => null]);
        $this->assertFalse(app(ProgrammeAccessService::class)->visibleQuery()->whereKey($p->id)->exists());
    }

    public function test_original_document_cannot_be_read_by_another_teacher(): void
    {
        $p = $this->importer();
        $other = User::factory()->create(['statut' => 'actif']);
        $other->assignRole('Enseignant');
        $doc = Document::findOrFail($p->document_source_id);
        $this->assertTrue(app(DocumentServiceContract::class)->peutAcceder($this->user, $doc));
        $this->assertFalse(app(DocumentServiceContract::class)->peutAcceder($other, $doc));
    }

    public function test_programme_without_progress_reports_missing_session_and_zero(): void
    {
        $p = $this->valide();
        $s = $this->creerSeance();
        $m = app(ProgressionMonitoringService::class)->mesure($p);
        $this->assertSame(0.0, $m['pourcentage']);
        $this->assertNull($m['temps']);
        $this->assertStringContainsString('1 séance', $m['alertes']);
    }

    public function test_cyclic_structure_is_rejected(): void
    {
        $p = $this->analyser($this->importer());
        $elements = app(ProgramStructureService::class)->proposition($p);
        $elements[0]['parent_index'] = 1;
        $this->expectException(ValidationException::class);
        app(ProgramStructureService::class)->enregistrer($p, $elements, true);
    }

    public function test_progression_modal_creates_a_declaration(): void
    {
        $p = $this->valide();
        $s = $this->creerSeance();
        Livewire::test(ListProgressions::class)
            ->callAction('create', data: $this->progressionData($p, $s))->assertHasNoActionErrors();
        $this->assertDatabaseHas('progressions', ['seance_id' => $s->id, 'etat_avancement' => 'commencee']);
    }

    public function test_structure_modal_validates_human_corrections(): void
    {
        $p = $this->analyser($this->importer());
        Livewire::test(ListProgrammes::class)
            ->callTableAction('structure', $p, data: ['elements' => app(ProgramStructureService::class)->proposition($p), 'valider' => true])->assertHasNoTableActionErrors();
        $this->assertSame('valide',$p->fresh()->statut);
    }

    public function test_suspended_user_loses_import_access(): void
    {
        $this->user->update(['statut' => 'suspendu']);
        $this->assertSame([],app(ProgrammeAccessService::class)->offres($this->year->id));
    }
}
