<?php

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\DocumentExtractor;
use App\Modules\Pedagogie\Contracts\ProgramDocumentAnalyzer;
use App\Modules\Pedagogie\Jobs\AnalyseProgramme;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Programme;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\Pedagogie\Services\ProgramImportService;
use App\Modules\Pedagogie\Services\ProgramStructureService;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Contracts\DocumentServiceContract;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

trait ProgrammesProgressionFixture
{
    private User $user;

    private Enseignant $teacher;

    private AnneeScolaire $year;

    private Classe $class;

    private OffrePedagogique $offer;

    private AffectationPedagogique $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
        Storage::fake('documents');
        $this->year = AnneeScolaire::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active']);
        $cycle = Cycle::create(['code' => 'PRI-T', 'nom' => 'Primaire', 'ordre' => 1]);
        $niveau = Niveau::create(['cycle_id' => $cycle->id, 'code' => 'CM-T', 'nom' => 'CM1', 'ordre' => 1]);
        $this->class = Classe::create(['nom' => 'CM1 A', 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $this->year->id, 'capacite_max' => 30, 'statut' => 'active']);
        $this->user = User::factory()->create(['statut' => 'actif', 'niveau_id' => null]);
        $this->user->assignRole('Enseignant');
        Permission::findOrCreate('module.pedagogie', 'web');
        $this->user->givePermissionTo('module.pedagogie');
        $employee = Employe::create(['user_id' => $this->user->id, 'matricule' => 'E-01', 'nom' => 'Test', 'prenom' => 'Marie', 'poste' => 'Enseignant', 'departement' => 'Pédagogie', 'date_embauche' => '2026-09-01', 'statut' => 'actif']);
        $this->teacher = Enseignant::create(['employe_id' => $employee->id, 'statut_contractuel' => 'titulaire']);
        $matiere = Matiere::create(['nom' => 'Mathématiques', 'code' => 'M-T', 'coefficient' => 1, 'coefficient_defaut' => 1, 'actif' => true]);
        $this->offer = OffrePedagogique::create(['matiere_id' => $matiere->id, 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $this->year->id, 'coefficient_matiere' => 1, 'actif' => true]);
        $this->assignment = AffectationPedagogique::create(['enseignant_id' => $this->teacher->id, 'classe_id' => $this->class->id, 'annee_scolaire_id' => $this->year->id, 'type' => 'principal', 'actif' => true, 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31']);
        $this->actingAs($this->user);
    }

    private function file(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'programme').'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Unité 1 : Nombres</w:t></w:r></w:p><w:p><w:r><w:t>Leçon 1 : Addition</w:t></w:r></w:p><w:p><w:r><w:t>Objectif : Compter</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile($path, 'programme.docx', null, null, true);
    }

    private function importer(): Programme
    {
        return app(ProgramImportService::class)->importer($this->year->id, $this->class->id, $this->offer->id, $this->file());
    }

    private function analyser(Programme $p): Programme
    {
        (new AnalyseProgramme($p->id))->handle(app(DocumentExtractor::class), app(ProgramDocumentAnalyzer::class), app(DocumentServiceContract::class));

        return $p->fresh();
    }

    private function valide(): Programme
    {
        $p = $this->analyser($this->importer());

        return app(ProgramStructureService::class)->enregistrer($p, app(ProgramStructureService::class)->proposition($p), true)->fresh();
    }

    private function creerSeance(): Seance
    {
        $slot = CreneauHoraire::create(['jour_semaine' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00']);
        $cours = EmploiDuTemps::create(['classe_id' => $this->class->id, 'matiere_id' => $this->offer->matiere_id, 'enseignant_id' => $this->teacher->id, 'salle_id' => 1, 'creneau_id' => $slot->id, 'annee_scolaire_id' => $this->year->id, 'actif' => true]);

        return Seance::create(['emploi_du_temps_id' => $cours->id, 'enseignant_id' => $this->teacher->id, 'date_seance' => '2026-10-05', 'statut' => 'dispensee']);
    }

    private function progressionData(Programme $p, Seance $s, array $extra = []): array
    {
        return array_replace(['seance_id' => $s->id, 'chapitre_id' => $p->chapitres()->where('type', 'lecon')->firstOrFail()->id, 'etat_avancement' => 'commencee', 'contenu_couvert' => 'Addition des entiers', 'devoir_donne' => false], $extra);
    }
}
