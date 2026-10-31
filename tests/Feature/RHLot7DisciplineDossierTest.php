<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\RH\Contracts\DisciplinePersonnelServiceContract;
use App\Modules\RH\Contracts\EmployeServiceContract;
use App\Modules\RH\Models\Contrat;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\RH\Models\PersonnelPosteHistorique;
use App\Modules\RH\Models\PosteAdministratif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RHLot7DisciplineDossierTest extends TestCase
{
    use RefreshDatabase;

    private DisciplinePersonnelServiceContract $disciplineService;

    private EmployeServiceContract $employeService;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($this->adminUser);

        $this->disciplineService = app(DisciplinePersonnelServiceContract::class);
        $this->employeService = app(EmployeServiceContract::class);
    }

    private function creerEmployeTest(array $attributs = []): Employe
    {
        return Employe::create(array_merge([
            'matricule' => 'EMP-L7-'.uniqid(),
            'nom' => 'KOUAM',
            'prenom' => 'Jean',
            'email' => 'jean.kouam.'.uniqid().'@example.com',
            'date_embauche' => '2025-01-01',
            'poste' => 'Agent',
            'statut' => 'actif',
        ], $attributs));
    }

    public function test_1_sanction_rattachee_au_bon_employe(): void
    {
        $employeA = $this->creerEmployeTest(['nom' => 'ALIOU']);
        $employeB = $this->creerEmployeTest(['nom' => 'BELLA']);

        $sanction = $this->disciplineService->enregistrerSanction(
            $employeA->id,
            'avertissement_ecrit',
            'Retard injustifié répété',
            null,
            Carbon::now(),
            null,
            'Premier avertissement formel'
        );

        $this->assertDatabaseHas('sanctions_personnel', [
            'id' => $sanction->id,
            'employe_id' => $employeA->id,
            'type' => 'avertissement_ecrit',
            'motif' => 'Retard injustifié répété',
            'observations' => 'Premier avertissement formel',
            'statut' => 'en_attente_validation',
        ]);

        $this->assertCount(1, $employeA->sanctions);
        $this->assertCount(0, $employeB->sanctions);
        $this->assertEquals($employeA->id, $sanction->employe_id);
    }

    public function test_2_historique_sanction_conserve(): void
    {
        $employe = $this->creerEmployeTest();

        $s1 = $this->disciplineService->enregistrerSanction($employe->id, 'avertissement', 'Motif 1', null, Carbon::parse('2026-01-10'));
        $s2 = $this->disciplineService->enregistrerSanction($employe->id, 'blame', 'Motif 2', null, Carbon::parse('2026-02-15'));
        $s3 = $this->disciplineService->enregistrerSanction($employe->id, 'suspension', 'Motif 3', 3, Carbon::parse('2026-03-20'));

        $historique = $this->disciplineService->getHistoriqueSanctions($employe->id);

        $this->assertCount(3, $historique);
        $this->assertEquals($s3->id, $historique->first()->id);
        $this->assertEquals($s1->id, $historique->last()->id);
    }

    public function test_3_sanction_n_altere_pas_user_statut(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $validateur = User::factory()->create(['statut' => 'actif']);
        $employe = $this->creerEmployeTest(['user_id' => $user->id]);

        $sanction = $this->disciplineService->enregistrerSanction(
            $employe->id,
            'suspension',
            'Faute grave',
            5,
            Carbon::now()
        );

        $this->disciplineService->valider($sanction->id, $validateur->id);

        $user->refresh();
        $this->assertEquals('actif', $user->statut);
    }

    public function test_4_sanction_n_altere_pas_contrat_statut_ni_employe_statut(): void
    {
        $employe = $this->creerEmployeTest(['statut' => 'actif']);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'reference' => 'CTR-LOT7-001',
            'type' => 'CDI',
            'date_debut' => '2026-01-01',
            'statut' => 'actif',
            'categorie_paie' => 'fixe',
            'salaire_base' => 350000,
        ]);

        $employe->update(['contrat_id' => $contrat->id]);

        $sanction = $this->disciplineService->enregistrerSanction(
            $employe->id,
            'blame',
            'Non-respect des consignes de sécurité'
        );

        $this->disciplineService->valider($sanction->id, 1);

        $contrat->refresh();
        $employe->refresh();

        $this->assertEquals('actif', $contrat->statut);
        $this->assertEquals('actif', $employe->statut);
    }

    public function test_5_diplome_rattache_au_bon_employe(): void
    {
        $employeA = $this->creerEmployeTest(['nom' => 'MBARGA']);
        $employeB = $this->creerEmployeTest(['nom' => 'NDONGO']);

        $diplome = $this->employeService->ajouterDiplome($employeA->id, [
            'nom' => 'Master en Gestion des Ressources Humaines',
            'type' => 'academique',
            'etablissement' => 'Université de Yaoundé II',
            'annee_obtention' => 2021,
        ]);

        $this->assertDatabaseHas('diplomes_personnel', [
            'id' => $diplome->id,
            'personnel_id' => $employeA->id,
            'nom' => 'Master en Gestion des Ressources Humaines',
            'type' => 'academique',
            'etablissement' => 'Université de Yaoundé II',
            'annee_obtention' => 2021,
        ]);

        $this->assertCount(1, $employeA->diplomes);
        $this->assertCount(0, $employeB->diplomes);
        $this->assertEquals($employeA->id, $diplome->personnel_id);
    }

    public function test_6_employe_peut_avoir_plusieurs_qualifications_valides(): void
    {
        $employe = $this->creerEmployeTest();

        $this->employeService->ajouterDiplome($employe->id, [
            'nom' => 'Licence en Informatique',
            'type' => 'academique',
            'annee_obtention' => 2019,
        ]);

        $this->employeService->ajouterDiplome($employe->id, [
            'nom' => 'Certificat PMP',
            'type' => 'professionnel',
            'annee_obtention' => 2022,
        ]);

        $this->employeService->ajouterDiplome($employe->id, [
            'nom' => 'Certification ITIL v4',
            'type' => 'professionnel',
            'annee_obtention' => 2024,
        ]);

        $diplomes = $this->employeService->getDiplomes($employe->id);

        $this->assertCount(3, $diplomes);
        $this->assertEquals(3, $employe->diplomes()->count());
    }

    public function test_7_justificatif_document_ged_est_preserve(): void
    {
        $employe = $this->creerEmployeTest();

        $docSanctionId = DB::table('documents')->insertGetId([
            'nom' => 'convocation.pdf',
            'categorie' => 'discipline',
            'fichier_path' => 'documents/convocation.pdf',
            'mime_type' => 'application/pdf',
            'taille' => 1024,
            'documentable_type' => Employe::class,
            'documentable_id' => $employe->id,
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $docDiplomeId = DB::table('documents')->insertGetId([
            'nom' => 'diplome_doctorat.pdf',
            'categorie' => 'diplome',
            'fichier_path' => 'documents/diplome.pdf',
            'mime_type' => 'application/pdf',
            'taille' => 2048,
            'documentable_type' => Employe::class,
            'documentable_id' => $employe->id,
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sanction = $this->disciplineService->enregistrerSanction(
            $employe->id,
            'demande_explication',
            'Absence non justifiée lors d’une réunion de rentrée',
            null,
            Carbon::now(),
            $docSanctionId,
            'Copie de la convocation'
        );

        $diplome = $this->employeService->ajouterDiplome($employe->id, [
            'nom' => 'Doctorat en Sciences de Gestion',
            'type' => 'academique',
            'etablissement' => 'Université de Douala',
            'annee_obtention' => 2020,
            'document_id' => $docDiplomeId,
        ]);

        $this->assertDatabaseHas('sanctions_personnel', [
            'id' => $sanction->id,
            'document_justificatif_id' => $docSanctionId,
        ]);

        $this->assertDatabaseHas('diplomes_personnel', [
            'id' => $diplome->id,
            'document_id' => $docDiplomeId,
        ]);
    }

    public function test_8_employe_sans_user_supporte_sanctions_et_diplomes(): void
    {
        $employe = $this->creerEmployeTest(['user_id' => null]);
        $this->assertNull($employe->user_id);

        $sanction = $this->disciplineService->enregistrerSanction(
            $employe->id,
            'avertissement',
            'Retard constaté sans badge'
        );

        $diplome = $this->employeService->ajouterDiplome($employe->id, [
            'nom' => 'Certificat de Sécurité et Prévention',
            'type' => 'professionnel',
            'annee_obtention' => 2023,
        ]);

        $this->assertNotNull($sanction->id);
        $this->assertNotNull($diplome->id);
        $this->assertEquals($employe->id, $sanction->employe_id);
        $this->assertEquals($employe->id, $diplome->personnel_id);
    }

    public function test_9_enseignant_reste_base_sur_employe(): void
    {
        $employe = $this->creerEmployeTest(['nom' => 'FOKOU', 'prenom' => 'Armand']);
        $enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'specialite' => 'Mathématiques',
            'statut_contractuel' => 'contractuel',
        ]);

        $diplome = $this->employeService->ajouterDiplome($employe->id, [
            'nom' => 'DIPES II Mathématiques',
            'type' => 'professionnel',
            'etablissement' => 'ENS Yaoundé',
            'annee_obtention' => 2018,
        ]);

        $sanction = $this->disciplineService->enregistrerSanction(
            $employe->id,
            'avertissement_ecrit',
            'Retard dans la remise des relevés'
        );

        $this->assertEquals($enseignant->employe_id, $employe->id);
        $this->assertEquals(1, $enseignant->employe->diplomes->count());
        $this->assertEquals(1, $enseignant->employe->sanctions->count());
        $this->assertEquals('DIPES II Mathématiques', $enseignant->employe->diplomes->first()->nom);
    }

    public function test_10_aucune_donnee_pedagogique_introduite_dans_rh(): void
    {
        $champsEmployes = Schema::getColumnListing('employes');
        $champsDiplomes = Schema::getColumnListing('diplomes_personnel');
        $champsSanctions = Schema::getColumnListing('sanctions_personnel');

        $champsInterdits = ['classe_id', 'matiere_id', 'cours_id', 'professeur_principal', 'charge_horaire_cours'];

        foreach ($champsInterdits as $champ) {
            $this->assertNotContains($champ, $champsEmployes, "La table employes ne doit pas contenir {$champ}");
            $this->assertNotContains($champ, $champsDiplomes, "La table diplomes_personnel ne doit pas contenir {$champ}");
            $this->assertNotContains($champ, $champsSanctions, "La table sanctions_personnel ne doit pas contenir {$champ}");
        }
    }

    public function test_11_aucun_stockage_documentaire_parallele_dans_rh(): void
    {
        // RH réutilise la table 'documents' du Socle ou des chemins legacy légers
        $this->assertFalse(Schema::hasTable('rh_documents'), 'RH ne doit pas créer une table de documents parallèle.');
        $this->assertFalse(Schema::hasTable('personnel_documents'), 'RH ne doit pas créer une table de documents parallèle.');
    }

    public function test_12_dossier_personnel_complet_coherence_administrative(): void
    {
        $poste = PosteAdministratif::create([
            'nom' => 'Responsable Sécurité',
            'description' => 'Coordination de la sécurité',
            'actif' => true,
        ]);

        $employe = $this->creerEmployeTest([
            'nom' => 'TCHOUA',
            'prenom' => 'Samuel',
            'poste_administratif_id' => $poste->id,
        ]);

        $contrat = Contrat::create([
            'employe_id' => $employe->id,
            'reference' => 'CTR-DOSSIER-001',
            'type' => 'CDI',
            'date_debut' => '2025-01-01',
            'statut' => 'actif',
            'categorie_paie' => 'fixe',
            'salaire_base' => 450000,
        ]);

        $employe->update(['contrat_id' => $contrat->id]);

        // Carrière / historique
        PersonnelPosteHistorique::create([
            'employe_id' => $employe->id,
            'poste_administratif_id' => $poste->id,
            'date_debut' => '2025-01-01',
            'motif' => 'Affectation initiale',
        ]);

        // Diplômes
        $this->employeService->ajouterDiplome($employe->id, [
            'nom' => 'Master Sécurité des Systèmes',
            'type' => 'academique',
            'annee_obtention' => 2022,
        ]);

        // Sanctions
        $this->disciplineService->enregistrerSanction(
            $employe->id,
            'avertissement',
            'Rapport hebdomadaire en retard'
        );

        $employe->refresh()->loadMissing(['posteAdministratif', 'contratPrincipal', 'diplomes', 'sanctions', 'historiquePostes']);

        $this->assertEquals('Responsable Sécurité', $employe->posteAdministratif->nom);
        $this->assertEquals('CDI', $employe->contratPrincipal->type);
        $this->assertEquals(450000, (float) $employe->contratPrincipal->salaire_base);
        $this->assertCount(1, $employe->diplomes);
        $this->assertCount(1, $employe->sanctions);
        $this->assertGreaterThanOrEqual(1, $employe->historiquePostes->count());
    }
}
