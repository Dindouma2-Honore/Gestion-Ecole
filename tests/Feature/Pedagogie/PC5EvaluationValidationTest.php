<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\EvaluationServiceInterface;
use App\Modules\Pedagogie\Contracts\MatiereServiceInterface;
use App\Modules\Pedagogie\Contracts\NoteServiceInterface;
use App\Modules\Pedagogie\Exceptions\SujetExamenNonValideException;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Evaluation;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PC5EvaluationValidationTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Periode $periode;

    private Classe $classe;

    private Matiere $matiere;

    private Enseignant $enseignant;

    private User $teacherUser;

    private User $directeurUser;

    private User $fondateurUser;

    private User $unauthorizedUser;

    private EvaluationServiceInterface $evaluationService;

    private NoteServiceInterface $noteService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Enseignant', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Directeur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Fondateur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Eleve', 'guard_name' => 'web']);

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
        $niveau = Niveau::create(['nom' => '4ème', 'code' => '4EME', 'cycle_id' => $cycle->id, 'ordre' => 8]);
        $this->classe = Classe::create([
            'nom' => '4ème B',
            'code' => '4E-B',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
        ]);

        $createdMatiere = app(MatiereServiceInterface::class)->creer(nom: 'Physique-Chimie', code: 'PC-4', coefficient: 3.0);
        $this->matiere = Matiere::findOrFail($createdMatiere['id']);

        $this->teacherUser = User::factory()->create(['name' => 'Professeur Test']);
        $this->teacherUser->assignRole('Enseignant');

        $employe = Employe::create([
            'matricule' => 'EMP-ENS-01',
            'nom' => 'Test',
            'prenom' => 'Professeur',
            'poste' => 'Enseignant',
            'date_embauche' => '2026-09-01',
            'user_id' => $this->teacherUser->id,
        ]);

        $this->enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'statut_contractuel' => 'titulaire',
            'statut' => 'actif',
            'specialite' => 'Physique-Chimie',
        ]);

        AffectationPedagogique::create([
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
            'enseignant_id' => $this->enseignant->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'matiere',
            'type_affectation' => 'matiere',
            'actif' => true,
        ]);

        $this->directeurUser = User::factory()->create(['name' => 'Directeur Test']);
        $this->directeurUser->assignRole('Directeur');

        $this->fondateurUser = User::factory()->create(['name' => 'Fondateur Test']);
        $this->fondateurUser->assignRole('Fondateur');

        $this->unauthorizedUser = User::factory()->create(['name' => 'Élève Curieux']);
        $this->unauthorizedUser->assignRole('Eleve');

        $this->evaluationService = app(EvaluationServiceInterface::class);
        $this->noteService = app(NoteServiceInterface::class);
    }

    public function test_enseignant_affecte_planifie_evaluation_avec_statut_initial_soumis(): void
    {
        $this->actingAs($this->teacherUser);

        $evaluation = $this->evaluationService->proposerSujet(
            enseignantId: $this->teacherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Contrôle continu N°1',
            dateEvaluation: '2026-10-15',
            bareme: 20.0,
            sujet: null,
            anneeScolaireId: $this->annee->id,
            periodeId: $this->periode->id,
            typeEvaluation: 'controle',
            coefficient: 2.0,
        );

        $this->assertInstanceOf(Evaluation::class, $evaluation);
        $this->assertEquals('soumis', $evaluation->statut);
        $this->assertEquals(20.0, $evaluation->bareme);
        $this->assertEquals(2.0, $evaluation->coefficient_evaluation);
        $this->assertEquals($this->annee->id, $evaluation->annee_scolaire_id);
        $this->assertEquals($this->periode->id, $evaluation->periode_id);
        $this->assertNull($evaluation->valide_par);
        $this->assertFalse($evaluation->estValidee());
    }

    public function test_enseignant_non_affecte_ne_peut_pas_proposer_evaluation(): void
    {
        $otherUser = User::factory()->create(['name' => 'Autre Prof']);
        $otherUser->assignRole('Enseignant');
        $otherEmploye = Employe::create([
            'matricule' => 'EMP-ENS-99',
            'nom' => 'Autre',
            'prenom' => 'Prof',
            'poste' => 'Enseignant',
            'date_embauche' => '2026-09-01',
            'user_id' => $otherUser->id,
        ]);
        $otherEnseignant = Enseignant::create([
            'employe_id' => $otherEmploye->id,
            'statut_contractuel' => 'titulaire',
            'statut' => 'actif',
        ]);

        $this->actingAs($otherUser);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("L'enseignant n'est pas affecté à cette classe et matière.");

        $this->evaluationService->proposerSujet(
            enseignantId: $otherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Tentative intruse',
            dateEvaluation: '2026-10-15',
            bareme: 20.0,
            sujet: null,
            anneeScolaireId: $this->annee->id,
            periodeId: $this->periode->id,
        );
    }

    public function test_bareme_ou_coefficient_invalide_rejete(): void
    {
        $this->actingAs($this->teacherUser);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Le barème doit être strictement positif.');

        $this->evaluationService->proposerSujet(
            enseignantId: $this->teacherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Devoir barème nul',
            dateEvaluation: '2026-10-15',
            bareme: 0.0,
        );
    }

    public function test_periode_invalide_pour_annee_scolaire_rejetee(): void
    {
        $this->actingAs($this->teacherUser);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("La période sélectionnée ne correspond pas à l'année scolaire active.");

        $this->evaluationService->proposerSujet(
            enseignantId: $this->teacherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Devoir période hors année',
            dateEvaluation: '2026-10-15',
            bareme: 20.0,
            anneeScolaireId: $this->annee->id,
            periodeId: 99999,
        );
    }

    public function test_utilisateur_non_autorise_ne_peut_pas_valider_evaluation(): void
    {
        $this->actingAs($this->teacherUser);

        $evaluation = $this->evaluationService->proposerSujet(
            enseignantId: $this->teacherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Évaluation en attente',
            dateEvaluation: '2026-10-15',
            bareme: 20.0,
            anneeScolaireId: $this->annee->id,
            periodeId: $this->periode->id,
        );

        // L'enseignant essaie de valider lui-même
        $this->expectException(AuthorizationException::class);
        $this->evaluationService->validerSujet($evaluation->id, $this->teacherUser->id);
    }

    public function test_fondateur_ou_directeur_valide_evaluation_avec_succes(): void
    {
        $evaluation = $this->evaluationService->proposerSujet(
            enseignantId: $this->teacherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Examen de synthèse',
            dateEvaluation: '2026-10-15',
            bareme: 40.0,
            anneeScolaireId: $this->annee->id,
            periodeId: $this->periode->id,
        );

        $this->evaluationService->validerSujet($evaluation->id, $this->fondateurUser->id);

        $evalFresh = $evaluation->fresh();
        $this->assertEquals('valide', $evalFresh->statut);
        $this->assertEquals($this->fondateurUser->id, $evalFresh->valide_par);
        $this->assertTrue($evalFresh->estValidee());
        $this->assertNull($evalFresh->motif_rejet);
    }

    public function test_rejet_avec_motif_par_autorite_et_rejet_sans_motif_interdit(): void
    {
        $evaluation = $this->evaluationService->proposerSujet(
            enseignantId: $this->teacherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Devoir incomplet',
            dateEvaluation: '2026-10-15',
            bareme: 20.0,
            anneeScolaireId: $this->annee->id,
            periodeId: $this->periode->id,
        );

        // Rejet sans motif -> exception
        try {
            $this->evaluationService->rejeterSujet($evaluation->id, '   ', $this->directeurUser->id);
            $this->fail('Rejet sans motif aurait dû échouer');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('motif de rejet est obligatoire', $e->getMessage());
        }

        // Rejet valide par Directeur avec motif
        $this->evaluationService->rejeterSujet($evaluation->id, 'Barème non conforme aux directives académiques', $this->directeurUser->id);

        $evalFresh = $evaluation->fresh();
        $this->assertEquals('rejete', $evalFresh->statut);
        $this->assertEquals('Barème non conforme aux directives académiques', $evalFresh->motif_rejet);
        $this->assertEquals($this->directeurUser->id, $evalFresh->valide_par);
    }

    public function test_saisie_note_impossible_si_evaluation_non_validee_et_autorisee_apres_validation(): void
    {
        $evaluation = $this->evaluationService->proposerSujet(
            enseignantId: $this->teacherUser->id,
            matiereId: $this->matiere->id,
            classeId: $this->classe->id,
            titre: 'Devoir avant validation',
            dateEvaluation: now()->subDay()->format('Y-m-d'),
            bareme: 20.0,
            anneeScolaireId: $this->annee->id,
            periodeId: $this->periode->id,
        );

        // Évaluation en statut 'soumis' -> Saisie impossible
        try {
            $this->noteService->enregistrer($evaluation->id, 501, 14.5);
            $this->fail('La saisie de note aurait dû être bloquée tant que le sujet n’est pas validé');
        } catch (SujetExamenNonValideException $e) {
            $this->assertStringContainsString('soumis', $e->getMessage());
        }

        // Validation par le directeur
        $this->evaluationService->validerSujet($evaluation->id, $this->directeurUser->id);

        // Saisie maintenant autorisée
        $note = $this->noteService->enregistrer($evaluation->id, 501, 14.5);
        $this->assertNotNull($note);
        $this->assertEquals(14.5, $note->valeur);
    }
}
