<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Exceptions\DeblocageNoteInterditException;
use App\Modules\Pedagogie\Exceptions\NoteModificationVerrouilleeException;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\Socle\Settings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PedagogieNoteLockTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public function test_note_is_locked_after_configured_delay(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        $evaluation = $this->evaluation();
        $service = app(NoteServiceInterface::class);
        $note = $service->enregistrer($evaluation->id, 101, 12);
        Carbon::setTestNow('2026-09-07 08:00:00');
        $service->enregistrer($evaluation->id, 101, 14);
        $this->assertSame(14.0, Note::findOrFail($note->id)->valeur);
        Carbon::setTestNow('2026-09-09 08:00:00');
        $this->expectException(NoteModificationVerrouilleeException::class);
        $service->enregistrer($evaluation->id, 101, 15);
    }

    public function test_only_founder_can_unlock_and_one_correction_relocks_immediately(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        $evaluation = $this->evaluation();
        $service = app(NoteServiceInterface::class);
        $note = $service->enregistrer($evaluation->id, 102, 10);
        Carbon::setTestNow('2026-09-09 08:00:00');

        Role::firstOrCreate(['name' => 'Enseignant']);
        $enseignant = User::factory()->create(['statut' => 'actif']);
        $enseignant->assignRole('Enseignant');
        $this->actingAs($enseignant);
        try {
            $service->debloquer($note->id, 'Erreur de transcription constatée');
            $this->fail('Un enseignant ne doit pas déverrouiller une note.');
        } catch (DeblocageNoteInterditException) {
            $this->assertTrue(true);
        }

        Role::firstOrCreate(['name' => 'Fondateur']);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);
        $service->debloquer($note->id, 'Erreur de transcription constatée');
        $this->assertTrue($service->peutModifier($note->id));
        $service->enregistrer($evaluation->id, 102, 13);
        $this->assertFalse($service->peutModifier($note->id));
        $this->assertNotNull(Note::findOrFail($note->id)->deblocage_consomme_le);
        $this->expectException(NoteModificationVerrouilleeException::class);
        $service->enregistrer($evaluation->id, 102, 14);
    }

    public function test_lock_delay_is_configurable(): void
    {
        $settings = app(SystemSettings::class);
        $settings->delaiModificationNotesJours = 2;
        $settings->save();
        Carbon::setTestNow('2026-09-01 08:00:00');
        $evaluation = $this->evaluation();
        $note = app(NoteServiceInterface::class)->enregistrer($evaluation->id, 103, 16);
        $this->assertSame('2026-09-03 08:00:00', app(NoteServiceInterface::class)->dateVerrouillage($note->id)->format('Y-m-d H:i:s'));
    }

    private function evaluation(): Evaluation
    {
        $matiere = Matiere::create(['nom' => 'Mathématiques', 'code' => 'MATH-T', 'coefficient' => 1, 'actif' => true]);
        return Evaluation::create(['titre' => 'Devoir de contrôle', 'matiere_id' => $matiere->id, 'classe_id' => 1, 'date_evaluation' => now()->toDateString(), 'bareme' => 20]);
    }
}
