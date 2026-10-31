<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\PresenceServiceInterface;
use App\Modules\Pedagogie\Exceptions\AppelDejaEffectueException;
use App\Modules\Pedagogie\Filament\Resources\SeanceResource\Pages\ListSeances;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\AnomalieAppel;
use App\Modules\Pedagogie\Models\CreneauHoraire;
use App\Modules\Pedagogie\Models\EmploiDuTemps;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\Presence;
use App\Modules\Pedagogie\Models\Seance;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PC4AppelPresenceAssiduiteTest extends TestCase
{
    use RefreshDatabase;

    private User $userEnseignant;

    private Enseignant $enseignant;

    private AnneeScolaire $annee;

    private Classe $classe;

    private OffrePedagogique $offre;

    private AffectationPedagogique $affectation;

    private Matiere $matiere;

    private EmploiDuTemps $emploiDuTemps;

    private Seance $seance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $cycle = Cycle::create(['code' => 'COL-PC4', 'nom' => 'Collège', 'ordre' => 2]);
        $niveau = Niveau::create(['cycle_id' => $cycle->id, 'code' => '5EME-PC4', 'nom' => '5ème', 'ordre' => 2]);

        $this->classe = Classe::create([
            'nom' => '5ème B',
            'niveau_id' => $niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'capacite_max' => 30,
            'statut' => 'active',
        ]);

        $this->userEnseignant = User::factory()->create([
            'statut' => 'actif',
            'niveau_id' => null,
        ]);
        $this->userEnseignant->assignRole('Enseignant');
        Permission::findOrCreate('module.pedagogie', 'web');
        $this->userEnseignant->givePermissionTo('module.pedagogie');

        $employe = Employe::create([
            'user_id' => $this->userEnseignant->id,
            'matricule' => 'E-PC4-01',
            'nom' => 'Nkono',
            'prenom' => 'Thomas',
            'poste' => 'Enseignant',
            'departement' => 'Pédagogie',
            'date_embauche' => '2026-09-01',
            'statut' => 'actif',
        ]);

        $this->enseignant = Enseignant::create([
            'employe_id' => $employe->id,
            'statut_contractuel' => 'titulaire',
        ]);

        $this->matiere = Matiere::create([
            'nom' => 'Mathématiques 5ème',
            'code' => 'MATH-PC4',
            'nature' => 'matiere',
            'coefficient' => 4,
            'coefficient_defaut' => 4,
            'actif' => true,
        ]);

        $this->offre = OffrePedagogique::create([
            'matiere_id' => $this->matiere->id,
            'niveau_id' => $niveau->id,
            'classe_id' => $this->classe->id,
            'annee_scolaire_id' => $this->annee->id,
            'coefficient_matiere' => 4,
            'actif' => true,
        ]);

        $this->affectation = AffectationPedagogique::create([
            'enseignant_id' => $this->enseignant->id,
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
            'offre_pedagogique_id' => $this->offre->id,
            'annee_scolaire_id' => $this->annee->id,
            'type' => 'matiere',
            'actif' => true,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
        ]);

        $creneau = CreneauHoraire::create([
            'jour_semaine' => 1,
            'heure_debut' => '08:00:00',
            'heure_fin' => '10:00:00',
            'ordre' => 1,
        ]);

        $this->emploiDuTemps = EmploiDuTemps::create([
            'creneau_id' => $creneau->id,
            'salle_id' => 10,
            'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id,
            'enseignant_id' => $this->enseignant->id,
            'annee_scolaire_id' => $this->annee->id,
            'statut' => 'publie',
            'actif' => true,
        ]);

        $this->seance = Seance::create([
            'emploi_du_temps_id' => $this->emploiDuTemps->id,
            'enseignant_id' => $this->enseignant->id,
            'date_seance' => '2026-10-05',
            'statut' => 'programmee',
            'progression_renseignee' => false,
        ]);

        $this->actingAs($this->userEnseignant);
    }

    public function test_pc4_1_liste_officielle_et_presents_par_defaut(): void
    {
        // 1. Mock du contrat Scolarité EleveServiceInterface retournant la liste officielle
        $mockEleves = [
            ['id' => 101, 'nom' => 'Abega', 'prenom' => 'Jean'],
            ['id' => 102, 'nom' => 'Beyala', 'prenom' => 'Calixthe'],
            ['id' => 103, 'nom' => 'Chamba', 'prenom' => 'David'],
            ['id' => 104, 'nom' => 'Dongmo', 'prenom' => 'Elise'],
        ];

        $eleveServiceMock = $this->mock(EleveServiceInterface::class);
        $eleveServiceMock->shouldReceive('getElevesParClasse')
            ->with($this->classe->id)
            ->andReturn($mockEleves);

        // 2. L'enseignant ne déclare que les anomalies : 102 absent, 103 retard (avec heure)
        // Les élèves 101 et 104 ne sont pas saisis -> DOIVENT être enregistrés 'present' par défaut
        $anomaliesSaisies = [
            102 => 'absent',
            103 => [
                'statut' => 'retard',
                'heure_arrivee' => '08:15',
            ],
        ];

        $presenceService = app(PresenceServiceInterface::class);
        $presenceService->faireAppel($this->seance->id, $anomaliesSaisies);

        // 3. Vérifications en base : 4 enregistrements créés
        $this->assertDatabaseCount('presences', 4);

        // 101 non mentionné -> 'present' par défaut
        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 101,
            'statut' => 'present',
        ]);

        // 102 saisi 'absent'
        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 102,
            'statut' => 'absent',
        ]);

        // 103 saisi 'retard' avec heure
        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 103,
            'statut' => 'retard',
            'heure_arrivee' => '08:15',
        ]);

        // 104 non mentionné -> 'present' par défaut
        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 104,
            'statut' => 'present',
        ]);

        // La séance est passée à 'dispensee'
        $this->assertEquals('dispensee', $this->seance->fresh()->statut);
    }

    public function test_pc4_2_autorisation_appel_enseignant_affecte_vs_intrus(): void
    {
        $mockEleves = [
            ['id' => 201, 'nom' => 'Test', 'prenom' => 'Eleve'],
        ];
        $eleveServiceMock = $this->mock(EleveServiceInterface::class);
        $eleveServiceMock->shouldReceive('getElevesParClasse')->andReturn($mockEleves);

        // 1. Un intrus non affecté tente de faire l'appel -> refusé (DomainException)
        $intrus = User::factory()->create(['statut' => 'actif']);
        $intrus->assignRole('Enseignant');
        $this->actingAs($intrus);

        $presenceService = app(PresenceServiceInterface::class);

        try {
            $presenceService->faireAppel($this->seance->id, [201 => 'present']);
            $this->fail('Un enseignant non affecté à la séance ne doit pas pouvoir faire l\'appel.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString("pas autorisé à faire l'appel", $e->getMessage());
        }

        // 2. L'enseignant titulaire affecté peut faire l'appel
        $this->actingAs($this->userEnseignant);
        $presenceService->faireAppel($this->seance->id, [201 => 'present']);

        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 201,
            'statut' => 'present',
        ]);
    }

    public function test_pc4_3_idempotence_et_mise_a_jour_sans_doublon(): void
    {
        $mockEleves = [
            ['id' => 301, 'nom' => 'Eleve', 'prenom' => 'Un'],
            ['id' => 302, 'nom' => 'Eleve', 'prenom' => 'Deux'],
        ];
        $eleveServiceMock = $this->mock(EleveServiceInterface::class);
        $eleveServiceMock->shouldReceive('getElevesParClasse')->andReturn($mockEleves);

        $presenceService = app(PresenceServiceInterface::class);

        // 1. Premier appel : 301 absent, 302 présent
        $presenceService->faireAppel($this->seance->id, [301 => 'absent', 302 => 'present']);
        $this->assertDatabaseCount('presences', 2);

        // 2. Deuxième appel sans le flag remplacer -> AppelDejaEffectueException
        try {
            $presenceService->faireAppel($this->seance->id, [301 => 'present']);
            $this->fail('Un deuxième appel brut doit lever AppelDejaEffectueException.');
        } catch (AppelDejaEffectueException) {
            // Comportement attendu
        }

        // 3. Deuxième sauvegarde avec remplacer = true (ou via l'UI Filament)
        // 301 est arrivé en retard à 08:20
        $presenceService->faireAppel($this->seance->id, [
            301 => ['statut' => 'retard', 'heure_arrivee' => '08:20'],
        ], remplacer: true);

        // IDEMPOTENCE : toujours 2 enregistrements en base, aucun doublon !
        $this->assertDatabaseCount('presences', 2);
        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 301,
            'statut' => 'retard',
            'heure_arrivee' => '08:20',
        ]);
        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 302,
            'statut' => 'present',
        ]);
    }

    public function test_pc4_4_resolution_automatique_anomalie_appel(): void
    {
        // 1. Création d'une anomalie d'appel manquant sur la séance
        $anomalie = AnomalieAppel::create([
            'seance_id' => $this->seance->id,
            'detectee_le' => now(),
            'notifiee' => false,
            'resolue' => false,
        ]);

        $this->assertFalse($anomalie->fresh()->resolue);

        $mockEleves = [
            ['id' => 401, 'nom' => 'Fokou', 'prenom' => 'Alain'],
        ];
        $eleveServiceMock = $this->mock(EleveServiceInterface::class);
        $eleveServiceMock->shouldReceive('getElevesParClasse')->andReturn($mockEleves);

        // 2. L'enseignant fait l'appel
        app(PresenceServiceInterface::class)->faireAppel($this->seance->id, []);

        // 3. L'anomalie d'appel est automatiquement marquée comme résolue
        $this->assertTrue($anomalie->fresh()->resolue);
    }

    public function test_pc4_5_appel_verrouille_refuse_toute_modification(): void
    {
        $presenceService = app(PresenceServiceInterface::class);
        $presenceService->verrouillerAppel($this->seance->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('verrouillé');

        $presenceService->faireAppel($this->seance->id, [101 => 'present'], remplacer: true);
    }

    public function test_pc4_6_filament_action_faire_appel_dans_seance_resource(): void
    {
        $mockEleves = [
            ['id' => 501, 'nom' => 'Gwet', 'prenom' => 'Michel'],
            ['id' => 502, 'nom' => 'Hassana', 'prenom' => 'Oumar'],
        ];
        $eleveServiceMock = $this->mock(EleveServiceInterface::class);
        $eleveServiceMock->shouldReceive('getElevesParClasse')->andReturn($mockEleves);

        // Test Livewire de l'action 'faireAppel' sur ListSeances
        Livewire::test(ListSeances::class)
            ->callTableAction('faireAppel', $this->seance, data: [
                'absents' => [501],
                'retards' => [
                    ['eleve_id' => 502, 'heure_arrivee' => '08:10'],
                ],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('presences', [
            'seance_id' => $this->seance->id,
            'eleve_id' => 501,
            'statut' => 'absent',
        ]);

        $presenceRetard = Presence::where('seance_id', $this->seance->id)->where('eleve_id', 502)->first();
        $this->assertNotNull($presenceRetard);
        $this->assertEquals('retard', $presenceRetard->statut);
        $this->assertStringStartsWith('08:10', (string) $presenceRetard->heure_arrivee);

        $this->assertEquals('dispensee', $this->seance->fresh()->statut);
    }
}
