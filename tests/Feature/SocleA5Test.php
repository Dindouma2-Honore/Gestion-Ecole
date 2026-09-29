<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Exceptions\TailleFichierDepasseeException;
use App\Modules\Socle\Exceptions\TypeFichierNonAutoriseException;
use App\Modules\Socle\Notifications\DocumentExpirationProche;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class SocleA5Test extends TestCase
{
    use RefreshDatabase;

    private DocumentServiceContract $documents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('documents');
        $this->documents = app(DocumentServiceContract::class);
    }

    public function test_document_is_attached_to_any_persisted_model_on_private_disk(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $eleve = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);

        $document = $this->documents->attacher(
            $eleve,
            UploadedFile::fake()->create('identite.pdf', 250, 'application/pdf'),
            'piece_identite',
            'restreint',
            now()->addYear(),
        );

        Storage::disk('documents')->assertExists($document->fichier_path);
        $this->assertSame($eleve->getMorphClass(), $document->documentable_type);
        $this->assertSame($eleve->id, $document->documentable_id);
        $this->assertSame($auteur->id, $document->created_by);
        $this->assertSame('restreint', $document->niveau_confidentialite);
        $this->assertSame('identite.pdf', $document->nom);
    }

    public function test_attachment_requires_authentication_and_never_falls_back_to_user_one(): void
    {
        $documentable = User::factory()->create(['statut' => 'actif']);

        $this->expectException(AccessDeniedHttpException::class);

        $this->documents->attacher(
            $documentable,
            UploadedFile::fake()->create('contrat.pdf', 50, 'application/pdf'),
            'contrat',
        );
    }

    public function test_file_type_and_size_are_validated_for_attachments_and_versions(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);

        try {
            $this->documents->attacher(
                $auteur,
                UploadedFile::fake()->create('virus.exe', 10),
                'piece_identite',
            );
            $this->fail('Une extension interdite aurait dû être refusée.');
        } catch (TypeFichierNonAutoriseException) {
            $this->assertDatabaseCount('documents', 0);
        }

        $document = $this->documents->attacher(
            $auteur,
            UploadedFile::fake()->create('contrat.pdf', 50, 'application/pdf'),
            'contrat',
        );

        $this->expectException(TailleFichierDepasseeException::class);
        $this->documents->nouvelleVersion(
            $document->id,
            UploadedFile::fake()->create('contrat-v2.pdf', 5121, 'application/pdf'),
        );
    }

    public function test_new_versions_are_numbered_without_overwriting_original_file(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);
        $document = $this->documents->attacher(
            $auteur,
            UploadedFile::fake()->create('contrat.pdf', 50, 'application/pdf'),
            'contrat',
        );
        $fichierOriginal = $document->fichier_path;

        $version1 = $this->documents->nouvelleVersion(
            $document->id,
            UploadedFile::fake()->create('contrat-v2.pdf', 60, 'application/pdf'),
        );
        $version2 = $this->documents->nouvelleVersion(
            $document->id,
            UploadedFile::fake()->create('contrat-v3.pdf', 70, 'application/pdf'),
        );

        $this->assertSame(1, $version1->version_numero);
        $this->assertSame(2, $version2->version_numero);
        $this->assertSame($fichierOriginal, $document->fresh()->fichier_path);
        Storage::disk('documents')->assertExists($version1->fichier_path);
        Storage::disk('documents')->assertExists($version2->fichier_path);
    }

    public function test_confidentiality_controls_access_and_filament_listing(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $enseignant = User::factory()->create(['statut' => 'actif']);
        $enseignant->assignRole('Enseignant');
        $this->actingAs($fondateur);

        $public = $this->documents->attacher($fondateur, UploadedFile::fake()->create('public.pdf', 10), 'note', 'public');
        $interne = $this->documents->attacher($fondateur, UploadedFile::fake()->create('interne.pdf', 10), 'note', 'interne');
        $restreint = $this->documents->attacher($fondateur, UploadedFile::fake()->create('secret.pdf', 10), 'contrat', 'restreint');

        $this->assertTrue($this->documents->peutAcceder($enseignant, $public));
        $this->assertTrue($this->documents->peutAcceder($enseignant, $interne));
        $this->assertFalse($this->documents->peutAcceder($enseignant, $restreint));
        $this->assertTrue($this->documents->peutAcceder($fondateur, $restreint));

        $this->actingAs($enseignant)
            ->get('/admin/documents')
            ->assertForbidden();

        $this->actingAs($fondateur)
            ->get('/admin/documents')
            ->assertSuccessful()
            ->assertSee('secret.pdf');
    }

    public function test_expiration_command_notifies_creator_and_ignores_expired_documents(): void
    {
        Notification::fake();
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);

        $bientot = $this->documents->attacher(
            $auteur,
            UploadedFile::fake()->create('bientot.pdf', 10),
            'certificat_medical',
            dateExpiration: now()->addDays(20),
        );
        $this->documents->attacher(
            $auteur,
            UploadedFile::fake()->create('expire.pdf', 10),
            'certificat_medical',
            dateExpiration: now()->subDay(),
        );

        $this->artisan('documents:alerter-expiration')->assertSuccessful();

        Notification::assertSentTo(
            $auteur,
            DocumentExpirationProche::class,
            fn (DocumentExpirationProche $notification): bool => true,
        );
        $this->assertCount(1, $this->documents->getDocumentsExpirantBientot(30));
        $this->assertSame($bientot->id, $this->documents->getDocumentsExpirantBientot(30)->first()->id);
    }
}
