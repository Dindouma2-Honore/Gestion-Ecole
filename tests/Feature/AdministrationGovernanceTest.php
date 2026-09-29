<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Pedagogie\Models\Bulletin;
use App\Modules\Socle\Contracts\DocumentTemplateServiceContract;
use App\Modules\Socle\Contracts\WorkflowServiceContract;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_published_template_creates_a_version_and_preserves_historical_rendering(): void
    {
        $service = app(DocumentTemplateServiceContract::class);
        $base = ['nom' => 'Bulletin secondaire', 'type_document' => 'bulletin', 'module_proprietaire' => 'Pédagogie', 'cycle' => 'secondaire', 'orientation' => 'portrait', 'format_papier' => 'A4', 'date_effet' => today()];
        $v1 = $service->publierNouvelleVersion('BUL-SEC', $base + ['contenu' => '<p>Version 1 — {{ $nom }}</p>']);
        $renduHistorique = $service->render($v1, ['nom' => 'Amina']);
        $bulletin = Bulletin::query()->create([
            'eleve_id' => 1, 'classe_id' => 1, 'annee_scolaire_id' => 1,
            'document_template_id' => $v1->id, 'document_template_version' => $v1->version,
            'generated_at' => now(), 'rendered_html' => $renduHistorique,
        ]);

        $v2 = $service->publierNouvelleVersion('BUL-SEC', $base + ['contenu' => '<p>Version 2 — {{ $nom }}</p>']);

        $this->assertSame(2, $v2->version);
        $this->assertFalse($v1->fresh()->actif);
        $this->assertSame('Version 1 — Amina', strip_tags($bulletin->fresh()->rendered_html));
        $this->assertSame(1, $bulletin->document_template_version);
        $this->expectException(\DomainException::class);
        $v1->update(['contenu' => 'altéré']);
    }

    public function test_workflow_rejects_without_motif_and_records_a_rejection(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $service = app(WorkflowServiceContract::class);
        $instance = $service->demarrerWorkflow('VALIDATION_DEPENSE', $fondateur, 'Finances', ['montant' => 150000, 'categorie_id' => 1]);

        try {
            $service->transitionner($instance->id, 'rejete', $fondateur->id, '');
            $this->fail('Le motif vide devait être refusé.');
        } catch (\DomainException) {
            $this->assertDatabaseCount('workflow_instance_transitions', 0);
        }

        $resultat = $service->transitionner($instance->id, 'rejete', $fondateur->id, 'Budget insuffisant');
        $this->assertSame('rejete', $resultat->statut);
        $this->assertDatabaseHas('workflow_instance_transitions', ['workflow_instance_id' => $instance->id, 'decision' => 'rejete', 'motif' => 'Budget insuffisant']);
    }
}
