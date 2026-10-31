<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Exceptions\DeblocageNoteInterditException;
use App\Modules\Pedagogie\Exceptions\NoteInvalideException;
use App\Modules\Pedagogie\Exceptions\NoteModificationVerrouilleeException;
use App\Modules\Pedagogie\Exceptions\SujetExamenNonValideException;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\Note;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PC6NotesApprobationGelTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Periode $periode;

    private Classe $classe;

    private Classe $autreClasse;

    private Matiere $matiere;

    private Eleve $eleve1;

    private Eleve $eleve2;

    private Eleve $eleveFantome;

    private User $teacherUser;

    private User $directeurUser;

    private User $fondateurUser;

    private EvaluationServiceInterface $evaluationService;

    private NoteServiceInterface $noteService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Enseignant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Directeur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Fondateur', 'guard_name' => 'web']);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $this->periode = Periode::create([
            'libelle' => 'Trimestre 1',
            'annee_scolaire_id' => $this->annee->id,
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-12-15',
            'statut' => 'ouverte',
            'type' => 'trimestre',
            'ordre' => 1,
        ]);

        $cycle = Cycle::create(['nom' => 'Collège', 'code' => 'COL', 'ordre' => 2]);
        $niveau = Niveau::create(['nom' => '3ème', 'code' => '3EME', 'cycle_id' => $cycle->id, 'ordre' => 9]);

        $this->classe = Classe::create([
            'nom' => '3ème A',
            'code' => '3E-A',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
        ]);

        $this->autreClasse = Classe::create([
            'nom' => '3ème B',
            'code' => '3E-B',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
        ]);

        $createdMatiere = app(MatiereServiceInterface::class)->creer(nom: 'Mathématiques', code: 'MATH-3', coefficient: 4.0);
        $this->matiere = Matiere::findOrFail($createdMatiere['id']);

        // Élèves officiels de la classe 3ème A
        $this->eleve1 = Eleve::create([
            'matricule' => 'MAT-2026-001',
            'nom' => 'Dupont',
            'prenom' => 'Alice',
            'date_naissance' => '2012-04-12',
            'sexe' => 'F',
        ]);
        Inscription::create([
            'eleve_id' => $this->eleve1->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'validee',
            'date_inscription' => '2026-09-01',
        ]);

        $this->eleve2 = Eleve::create([
            'matricule' => 'MAT-2026-002',
            'nom' => 'Martin',
            'prenom' => 'Bob',
            'date_naissance' => '2012-07-25',
            'sexe' => 'M',
        ]);
        Inscription::create([
            'eleve_id' => $this->eleve2->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'validee',
            'date_inscription' => '2026-09-01',
        ]);

        // Élève d'une autre classe (fantôme pour la classe 3ème A)
        $this->eleveFantome = Eleve::create([
            'matricule' => 'MAT-2026-099',
            'nom' => 'Inconnu',
            'prenom' => 'Paul',
            'date_naissance' => '2012-11-05',
            'sexe' => 'M',
        ]);
        Inscription::create([
            'eleve_id' => $this->eleveFantome->id,
            'classe_id' => $this->autreClasse->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'validee',
            'date_inscription' => '2026-09-01',
        ]);

        $this->teacherUser = User::factory()->create(['name' => 'Professeur Maths']);
        $this->teacherUser->assignRole('Enseignant');

        $employe = Employe::create([
            'matricule' => 'EMP-MATH-01',
            'nom' => 'Maths',
            'prenom' => 'Prof',
            'poste' => 'Enseignant',
            'date_embauche' => '2026-09-01',
            'user_id' => $this->teacherUser->id,
        ]);
        Enseignant::create([
            'employe_id' => $employe->id,
            'statut_contractuel' => 'titulaire',
            'statut' => 'actif',
            'specialite' => 'Mathématiques',
        ]);

        $this->directeurUser = User::factory()->create(['name' => 'Directeur']);
        $this->directeurUser->assignRole('Directeur');

        $this->fondateurUser = User::factory()->create(['name' => 'Fondateur']);
        $this->fondateurUser->assignRole('Fondateur');

        $this->evaluationService = app(EvaluationServiceInterface::class);
        $this->noteService = app(NoteServiceInterface::class);
    }

    public function test_saisie_autorisee_uniquement_sur_evaluation_validee(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir surveillé',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'soumis',
        ]);

        // Refus sur évaluation soumise
        $this->expectException(SujetExamenNonValideException::class);
        $this->noteService->enregistrer($eval->id, $this->eleve1->id, 15.0);
    }

    public function test_eleve_fantome_ou_hors_classe_est_strictement_rejete(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir validé',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'valide',
        ]);

        // Élève d'une autre classe rejeté
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("n'appartient pas à la classe officielle");

        $this->noteService->enregistrer($eval->id, $this->eleveFantome->id, 14.0);
    }

    public function test_bareme_strictement_respecte(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir sur 20',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'valide',
        ]);

        // Note négative -> exception
        try {
            $this->noteService->enregistrer($eval->id, $this->eleve1->id, -1.0);
            $this->fail('Note négative acceptée');
        } catch (NoteInvalideException $e) {
            $this->assertStringContainsString('comprise entre 0 et 20', $e->getMessage());
        }

        // Note supérieure au barème -> exception
        try {
            $this->noteService->enregistrer($eval->id, $this->eleve1->id, 20.5);
            $this->fail('Note supérieure au barème acceptée');
        } catch (NoteInvalideException $e) {
            $this->assertStringContainsString('comprise entre 0 et 20', $e->getMessage());
        }
    }

    public function test_idempotence_et_pas_de_doublon_de_saisie(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir test unicité',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'valide',
        ]);

        // Première saisie
        $note1 = $this->noteService->enregistrer($eval->id, $this->eleve1->id, 12.0);
        $this->assertEquals(12.0, $note1->valeur);

        // Seconde saisie pour le même élève : mise à jour sans doublon
        $noteMaj = $this->noteService->enregistrer($eval->id, $this->eleve1->id, 14.5);
        $this->assertEquals(14.5, $noteMaj->valeur);
        $this->assertEquals($note1->id, $noteMaj->id);

        $nbNotes = Note::where('evaluation_id', $eval->id)->where('eleve_id', $this->eleve1->id)->count();
        $this->assertEquals(1, $nbNotes);
    }

    public function test_soumission_exige_completude_et_bloque_les_oublis(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir complet',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'valide',
        ]);

        // Saisie uniquement pour eleve1 (eleve2 oublié)
        $this->noteService->enregistrer($eval->id, $this->eleve1->id, 16.0);

        // Tentative de soumission avec élève manquant
        try {
            $this->noteService->soumettreNotes($eval->id, $this->teacherUser->id);
            $this->fail('Soumission avec oubli aurait dû échouer');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Saisie incomplète', $e->getMessage());
        }

        // On évalue eleve2 (par exemple absent justifié)
        $this->noteService->enregistrerAbsence($eval->id, $this->eleve2->id);

        // Soumission réussit maintenant
        $this->noteService->soumettreNotes($eval->id, $this->teacherUser->id);

        $notes = Note::where('evaluation_id', $eval->id)->get();
        foreach ($notes as $note) {
            $this->assertEquals('soumis', $note->statut);
            $this->assertEquals($this->teacherUser->id, $note->soumis_par);
            $this->assertNotNull($note->soumis_le);
        }
    }

    public function test_approbation_par_fondateur_et_rejet_pour_non_autorise(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir approbation',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'valide',
        ]);

        $this->noteService->enregistrer($eval->id, $this->eleve1->id, 15.0);
        $this->noteService->enregistrer($eval->id, $this->eleve2->id, 12.0);
        $this->noteService->soumettreNotes($eval->id, $this->teacherUser->id);

        // L'enseignant essaie d'approuver lui-même -> Rejet
        try {
            $this->noteService->approuverNotes($eval->id, $this->teacherUser->id);
            $this->fail('Enseignant ne doit pas pouvoir approuver ses propres notes');
        } catch (AuthorizationException $e) {
            $this->assertStringContainsString('Seul le Fondateur', $e->getMessage());
        }

        // Le Fondateur approuve
        $this->noteService->approuverNotes($eval->id, $this->fondateurUser->id);

        $evalFresh = $eval->fresh();
        $this->assertTrue($evalFresh->verrouillee);

        $notes = Note::where('evaluation_id', $eval->id)->get();
        foreach ($notes as $note) {
            $this->assertEquals('valide', $note->statut);
            $this->assertTrue($note->verrouillee);
            $this->assertEquals($this->fondateurUser->id, $note->valide_par);
            $this->assertNotNull($note->valide_le);
        }
    }

    public function test_gel_interdit_toute_modification_directe_et_autorise_deblocage_par_fondateur(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir gelé',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'valide',
        ]);

        $this->noteService->enregistrer($eval->id, $this->eleve1->id, 15.0);
        $this->noteService->enregistrer($eval->id, $this->eleve2->id, 12.0);
        $this->noteService->soumettreNotes($eval->id, $this->teacherUser->id);
        $this->noteService->approuverNotes($eval->id, $this->fondateurUser->id);

        // Tentative de modification directe de la note -> Refusée
        $this->expectException(NoteModificationVerrouilleeException::class);
        $this->noteService->enregistrer($eval->id, $this->eleve1->id, 18.0);
    }

    public function test_deblocage_exceptionnel_par_fondateur_avec_motif_autorise_correction_unique(): void
    {
        $eval = Evaluation::create([
            'titre' => 'Devoir pour déblocage',
            'matiere_id' => $this->matiere->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'periode_id' => $this->periode->id,
            'date_evaluation' => now()->subDay()->format('Y-m-d'),
            'bareme' => 20.0,
            'statut' => 'valide',
        ]);

        $this->noteService->enregistrer($eval->id, $this->eleve1->id, 15.0);
        $this->noteService->enregistrer($eval->id, $this->eleve2->id, 12.0);
        $this->noteService->soumettreNotes($eval->id, $this->teacherUser->id);
        $this->noteService->approuverNotes($eval->id, $this->fondateurUser->id);

        $note = Note::where('evaluation_id', $eval->id)->where('eleve_id', $this->eleve1->id)->firstOrFail();

        // Enseignant essaie de débloquer -> Échec
        $this->actingAs($this->teacherUser);
        try {
            $this->noteService->debloquer($note->id, 'Erreur de saisie');
            $this->fail('Enseignant ne peut pas débloquer une note');
        } catch (DeblocageNoteInterditException $e) {
            $this->assertStringContainsString('Seul le Fondateur', $e->getMessage());
        }

        // Fondateur débloque exceptionnellement la note avec motif
        $this->actingAs($this->fondateurUser);
        $this->noteService->debloquer($note->id, 'Erreur matérielle de transcription vérifiée sur copie papier');

        $noteFresh = $note->fresh();
        $this->assertNotNull($noteFresh->debloque_le);
        $this->assertNull($noteFresh->deblocage_consomme_le);

        // La correction unique est maintenant permise
        $this->actingAs($this->teacherUser);
        $noteCorrigee = $this->noteService->enregistrer($eval->id, $this->eleve1->id, 17.5);
        $this->assertEquals(17.5, $noteCorrigee->valeur);
        $this->assertNotNull($noteCorrigee->fresh()->deblocage_consomme_le);

        // Une tentative de réédition immédiate est à nouveau verrouillée
        $this->expectException(NoteModificationVerrouilleeException::class);
        $this->noteService->enregistrer($eval->id, $this->eleve1->id, 19.0);
    }
}
