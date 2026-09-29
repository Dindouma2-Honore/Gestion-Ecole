<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_peut_creer_un_document(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('certificat.pdf', 200, 'application/pdf');

        $document = Document::create([
            'nom' => 'Certificat de scolarité',
            'categorie' => 'Administratif',
            'fichier_path' => $file->store('documents', 'public'),
            'mime_type' => 'application/pdf',
            'taille' => 204800,
            'documentable_type' => User::class,
            'documentable_id' => $user->id,
            'niveau_confidentialite' => 'interne',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'nom' => 'Certificat de scolarité',
            'categorie' => 'Administratif',
        ]);
    }

    public function test_generation_pdf_fiche_document(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        $document = Document::create([
            'nom' => 'Document Test Non PDF',
            'categorie' => 'Pédagogique',
            'fichier_path' => 'documents/test.txt',
            'mime_type' => 'text/plain',
            'taille' => 500,
            'documentable_type' => User::class,
            'documentable_id' => $user->id,
            'niveau_confidentialite' => 'public',
            'created_by' => $user->id,
        ]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('socle::pdf.document-fiche', ['document' => $document]);
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }
}
