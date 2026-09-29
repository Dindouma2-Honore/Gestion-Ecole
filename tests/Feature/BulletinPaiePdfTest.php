<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Models\BulletinPaie;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Services\BulletinPaiePdfService;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplateDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulletinPaiePdfTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $anneeScolaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplateDocumentSeeder::class);

        $this->anneeScolaire = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);
    }

    public function test_des_modeles_de_documents_pdf_sont_bien_initialises(): void
    {
        $this->assertDatabaseHas('templates_documents', ['code' => 'BULLETIN_PAIE']);
        $this->assertDatabaseHas('templates_documents', ['code' => 'FACTURE_STANDARD']);
        $this->assertDatabaseHas('templates_documents', ['code' => 'CONTRAT_TRAVAIL']);
        $this->assertDatabaseHas('templates_documents', ['code' => 'LISTE_ELEVES']);
    }

    public function test_le_pdf_du_bulletin_de_paie_est_genere_correctement(): void
    {
        $user = User::factory()->create();
        $employe = Employe::create([
            'user_id' => $user->id,
            'matricule' => 'EMP-001',
            'nom' => 'KAMGA',
            'prenom' => 'Jean',
            'poste' => 'Enseignant de Mathématiques',
            'date_embauche' => now()->subYear(),
            'statut' => 'actif',
        ]);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'type' => 'CDI',
            'date_debut' => now()->subYear(),
            'salaire_base' => 250000.00,
            'statut' => 'actif',
        ]);

        $bulletin = BulletinPaie::create([
            'employe_id' => $employe->id,
            'contrat_id' => $contrat->id,
            'mois' => 8,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'salaire_base' => 250000.00,
            'total_primes' => 30000.00,
            'total_retenues' => 15000.00,
            'total_cotisations' => 10000.00,
            'avances_deduites' => 5000.00,
            'net_a_payer' => 265000.00,
            'statut' => 'valide',
            'date_paiement' => now(),
        ]);

        $service = app(BulletinPaiePdfService::class);
        $responseDownload = $service->telecharger($bulletin);

        $this->assertSame(200, $responseDownload->getStatusCode());
        $this->assertSame('application/pdf', $responseDownload->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $responseDownload->headers->get('Content-Disposition'));

        $responsePrint = $service->imprimer($bulletin);
        $this->assertSame(200, $responsePrint->getStatusCode());
        $this->assertStringContainsString('inline;', (string) $responsePrint->headers->get('Content-Disposition'));
    }

    public function test_les_routes_du_bulletin_de_paie_sont_accessibles_apres_authentification(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Fondateur');

        $employe = Employe::create([
            'user_id' => $admin->id,
            'matricule' => 'EMP-002',
            'nom' => 'EBANGA',
            'prenom' => 'Marie',
            'poste' => 'Secrétaire Général',
            'date_embauche' => now()->subMonths(6),
            'statut' => 'actif',
        ]);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'type' => 'CDD',
            'date_debut' => now(),
            'salaire_base' => 180000.00,
            'statut' => 'actif',
        ]);

        $bulletin = BulletinPaie::create([
            'employe_id' => $employe->id,
            'contrat_id' => $contrat->id,
            'mois' => 8,
            'annee' => 2026,
            'annee_scolaire_id' => $this->anneeScolaire->id,
            'salaire_base' => 180000.00,
            'net_a_payer' => 180000.00,
            'statut' => 'paye',
        ]);

        $this->actingAs($admin);

        $responsePdf = $this->get(route('rh.bulletins-paie.telecharger', $bulletin));
        $responsePdf->assertStatus(200);

        $responsePrint = $this->get(route('rh.bulletins-paie.imprimer', $bulletin));
        $responsePrint->assertStatus(200);

        $responsePrintAll = $this->get(route('rh.bulletins-paie.imprimer-tous', ['mois' => 8, 'annee' => 2026]));
        $responsePrintAll->assertStatus(200);
        $this->assertStringContainsString('inline;', (string) $responsePrintAll->headers->get('Content-Disposition'));
    }
}
