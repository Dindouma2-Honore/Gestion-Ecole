<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use App\Modules\Socle\Exceptions\TypeFichierNonAutoriseException;
use App\Modules\Socle\Models\Courrier;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Reunion;
use App\Modules\Socle\Models\Tache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplementaireTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_attacher_refuse_extension_non_autorisee(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('test.exe', 100);

        $documentService = app(DocumentServiceContract::class);

        $this->expectException(TypeFichierNonAutoriseException::class);
        $documentService->attacher($user, $file, 'pieces_identite');
    }

    public function test_courrier_workflow_transitions(): void
    {
        FormatNumerotation::create([
            'type_document' => 'courrier',
            'format' => 'COUR-{{annee}}-{{seq:3}}',
            'prochain_numero' => 1,
        ]);

        $courrierService = app(CourrierServiceContract::class);
        /** @var Courrier $courrier */
        $courrier = $courrierService->enregistrerCourrierEntrant([
            'objet' => 'Demande d inscription',
            'expediteur' => 'M. Dupont',
            'destinataire' => 'Secrétariat',
        ]);

        $this->assertEquals('recu', $courrier->statut);

        // Transition valide: recu -> affecte
        $courrier->changerStatut('affecte');
        $this->assertEquals('affecte', $courrier->fresh()->statut);

        // Transition invalide: affecte -> archive (doit lever TransitionStatutInvalideException)
        $this->expectException(TransitionStatutInvalideException::class);
        $courrier->changerStatut('archive');
    }

    public function test_reunion_decision_genere_tache_automatiquement(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $reunionService = app(ReunionServiceContract::class);
        /** @var Reunion $reunion */
        $reunion = $reunionService->planifierReunion(
            ['titre' => 'Conseil Pédagogique', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [$user->id],
            ['Revue des programmes']
        );

        $decision = $reunionService->ajouterDecision(
            $reunion->id,
            'Réviser les fiches de cours',
            $user->id,
            now()->addDays(7)
        );

        $this->assertNotNull($decision->tache_id);
        $tache = Tache::find($decision->tache_id);
        $this->assertNotNull($tache);
        $this->assertEquals('a_faire', $decision->statut);
    }
}
