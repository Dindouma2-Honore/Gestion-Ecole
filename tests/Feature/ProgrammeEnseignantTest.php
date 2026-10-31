<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\ProgrammeServiceInterface;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\ProgrammeChapitre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\Pedagogie\ProgrammesProgressionFixture;
use Tests\TestCase;

class ProgrammeEnseignantTest extends TestCase
{
    use ProgrammesProgressionFixture, RefreshDatabase;

    public function test_legacy_submission_entry_point_enforces_connected_teacher_identity(): void
    {
        $this->expectException(HttpException::class);
        app(ProgrammeServiceInterface::class)->soumettreProgramme(999, $this->offer->matiere_id, $this->class->id, $this->year->id, $this->file(), [], $this->offer->id);
    }

    public function test_submission_entry_point_uses_async_versioned_workflow(): void
    {
        $p = app(ProgrammeServiceInterface::class)->soumettreProgramme($this->teacher->id, $this->offer->matiere_id, $this->class->id, $this->year->id, $this->file(), [], $this->offer->id);
        $this->assertSame('analyse_en_attente', $p->analyse_statut);
        $this->assertSame($this->class->niveau_id, $p->niveau_id);
        $this->assertSame(0, $p->chapitres()->count());
    }

    public function test_validated_latest_version_supplies_session_elements(): void
    {
        $p = $this->valide();
        $v2 = $this->importer();
        $service = app(ProgrammeServiceInterface::class);
        $this->assertSame($p->chapitres()->pluck('id')->all(), $service->getChapitresPrevus($p->matiere_id, $p->classe_id, $p->annee_scolaire_id, $p->enseignant_id)->pluck('id')->all());
        $v2 = $this->analyser($v2);
        $service->validerProgramme($v2->id, $this->user->id);
        $this->assertSame($v2->chapitres()->pluck('id')->all(), $service->getChapitresPrevus($p->matiere_id, $p->classe_id, $p->annee_scolaire_id, $p->enseignant_id)->pluck('id')->all());
    }

    public function test_historical_rejection_keeps_official_fallback(): void
    {
        $u = User::factory()->create(['statut' => 'actif']);
        $u->assignRole('Fondateur');
        $this->actingAs($u);
        $official = Programme::create(['matiere_id' => $this->offer->matiere_id, 'niveau_id' => $this->class->niveau_id, 'annee_scolaire_id' => $this->year->id, 'source' => 'officiel', 'titre' => 'Référence historique', 'statut' => 'valide']);
        $chapter = ProgrammeChapitre::create(['programme_id' => $official->id, 'titre' => 'Chapitre historique', 'ordre' => 1]);
        $teacher = Programme::create(['matiere_id' => $this->offer->matiere_id, 'niveau_id' => $this->class->niveau_id, 'classe_id' => $this->class->id, 'annee_scolaire_id' => $this->year->id, 'source' => 'enseignant', 'enseignant_id' => $this->teacher->id, 'titre' => 'Ancien dépôt', 'statut' => 'soumis']);
        app(ProgrammeServiceInterface::class)->rejeterProgramme($teacher->id, 'Document incomplet');
        $this->assertSame('rejete', $teacher->fresh()->statut);
        $this->assertSame([$chapter->id], app(ProgrammeServiceInterface::class)->getChapitresPrevus($this->offer->matiere_id, $this->class->id, $this->year->id)->pluck('id')->all());
    }

    public function test_importer_chapitres_depuis_excel_parses_csv_rows(): void
    {
        $file = UploadedFile::fake()->createWithContent('chapitres.csv', "titre,ordre,objectifs\nNombres,1,Compter\nFractions,2,Comparer\n");
        $result = app(ProgrammeServiceInterface::class)->importerChapitresDepuisExcel($file);
        $this->assertCount(2,$result);
        $this->assertSame('Nombres',$result[0]['titre']);
    }
}
