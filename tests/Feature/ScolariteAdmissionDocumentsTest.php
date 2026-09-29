<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Scolarite\Models\DocumentEleve;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\TypeDocumentEleve;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScolariteAdmissionDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_required_document_type_appears_as_missing_without_blocking_admission(): void
    {
        $eleve = Eleve::create(['nom' => 'Doe', 'prenom' => 'Amina', 'statut' => 'prospect']);
        $type = TypeDocumentEleve::create(['code' => 'NAISSANCE', 'nom' => 'Acte de naissance', 'obligatoire' => true, 'actif' => true]);

        $this->assertTrue($eleve->fresh()->documents_obligatoires_manquants->contains($type));
        $this->assertDatabaseHas('eleves', ['id' => $eleve->id]);
    }

    public function test_attaching_the_file_removes_the_missing_document_badge(): void
    {
        $user = User::factory()->create();
        $eleve = Eleve::create(['nom' => 'Doe', 'prenom' => 'Amina', 'statut' => 'prospect']);
        $type = TypeDocumentEleve::create(['code' => 'PHOTO', 'nom' => 'Photo', 'obligatoire' => true, 'actif' => true]);
        DocumentEleve::create(['eleve_id' => $eleve->id, 'type_document_eleve_id' => $type->id, 'fichier' => 'eleves/documents/photo.jpg', 'date_ajout' => today(), 'ajoute_par' => $user->id]);

        $this->assertTrue($eleve->fresh()->documents_obligatoires_manquants->isEmpty());
    }

    public function test_pdf_view_only_contains_selected_columns(): void
    {
        $html = view('finances::pdf.situation-financiere', [
            'lignes' => [['eleve' => 'Amina Doe', 'classe' => 'CM2', 'du' => 100000]],
            'colonnes' => ['eleve', 'du'],
            'definitions' => ['eleve' => 'Nom élève', 'classe' => 'Classe', 'du' => 'Montant attendu'],
        ])->render();

        $this->assertStringContainsString('Nom élève', $html);
        $this->assertStringContainsString('Montant attendu', $html);
        $this->assertStringNotContainsString('<th>Classe</th>', $html);
    }

    public function test_scolarite_sidebar_contains_the_six_essential_sections(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');

        $this->actingAs($user)->get('/admin/eleves')->assertSuccessful()
            ->assertSee('Élèves &amp; admissions', escape: false)
            ->assertSee('Parents &amp; tuteurs', escape: false)
            ->assertSee('Inscriptions')
            ->assertSee('Classes')
            ->assertSee('Documents d’admission')
            ->assertSee('Bilan journalier')
            ->assertSee('Statut des paiements');
    }
}
