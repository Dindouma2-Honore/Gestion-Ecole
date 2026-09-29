<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\AuditServiceContract;
use App\Modules\Socle\Exceptions\MotifObligatoireException;
use App\Modules\Socle\Models\ActivityLog;
use App\Modules\Socle\Models\Document;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SocleA4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_sensitive_operation_requires_a_non_empty_reason(): void
    {
        $this->expectException(MotifObligatoireException::class);

        app(AuditServiceContract::class)->enregistrerAvecMotif(
            User::factory()->create(),
            'Annulation paiement',
            '   ',
        );
    }

    public function test_manual_audit_stores_actor_ip_reason_and_subject(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $this->actingAs($actor);

        app(AuditServiceContract::class)->enregistrerAvecMotif(
            $subject,
            'Suspension du compte',
            'Demande de la direction',
        );

        $activity = ActivityLog::firstOrFail();
        $this->assertSame($actor->id, $activity->causer_id);
        $this->assertSame($subject->id, $activity->subject_id);
        $this->assertSame('Demande de la direction', $activity->motif);
        $this->assertSame('127.0.0.1', $activity->ip_address);
    }

    public function test_sensitive_consultation_uses_dedicated_log_and_history_is_returned(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $this->actingAs($actor);
        $service = app(AuditServiceContract::class);

        $service->enregistrerConsultation($subject, 'Dossier confidentiel');

        $this->assertSame('consultation', ActivityLog::firstOrFail()->log_name);
        $this->assertSame('Consultation : Dossier confidentiel', $service->getHistorique($subject)->first()->description);
    }

    public function test_document_changes_are_logged_automatically_with_old_and_new_values(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $document = Document::create([
            'nom' => 'Contrat',
            'categorie' => 'administratif',
            'fichier_path' => 'documents/contrat.pdf',
            'mime_type' => 'application/pdf',
            'taille' => 1200,
            'documentable_type' => $actor->getMorphClass(),
            'documentable_id' => $actor->id,
            'niveau_confidentialite' => 'restreint',
            'created_by' => $actor->id,
        ]);

        $document->update(['nom' => 'Contrat corrigé']);

        $activity = ActivityLog::where('event', 'updated')->firstOrFail();
        $this->assertSame('Contrat', $activity->properties->get('old')['nom']);
        $this->assertSame('Contrat corrigé', $activity->properties->get('attributes')['nom']);
        $this->assertSame('127.0.0.1', $activity->ip_address);
    }

    public function test_audit_interface_is_read_only_and_founder_only(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $enseignant = User::factory()->create(['statut' => 'actif']);
        $enseignant->assignRole('Enseignant');

        $this->assertTrue(Gate::forUser($fondateur)->allows('viewAny', ActivityLog::class));
        $this->assertFalse(Gate::forUser($enseignant)->allows('viewAny', ActivityLog::class));
        $this->actingAs($fondateur)->get('/admin/activity-logs')->assertSuccessful();
        $this->actingAs($enseignant)->get('/admin/activity-logs')->assertForbidden();
    }
}
