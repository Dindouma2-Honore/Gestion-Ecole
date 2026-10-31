<?php

declare(strict_types=1);

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\AffectationPedagogiqueServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages\CreateMatiere;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages\ListMatieres;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource;
use App\Modules\Pedagogie\Models\CategorieMatiere;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OrganisationCycle;
use App\Modules\Pedagogie\Services\AffectationPedagogiqueService;
use App\Modules\Pedagogie\Services\AffectationService;
use App\Modules\Pedagogie\Services\CatalogueMatiereService;
use App\Modules\Pedagogie\Services\OffrePedagogiqueService;
use App\Modules\RH\Models\Employe;
use App\Modules\RH\Models\Enseignant;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Cycle;
use App\Modules\Scolarite\Models\Niveau;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PC1ProcessusCritiqueTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private AnneeScolaire $annee;

    private CategorieMatiere $categorie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->adminUser = User::factory()->create(['statut' => 'actif']);
        $this->adminUser->assignRole('Fondateur');
        $this->actingAs($this->adminUser);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $this->categorie = CategorieMatiere::query()->where('code', 'NON_CLASSEE')->firstOrFail();
    }

    private function creerStructure(string $cycleCode, string $cycleNom, string $niveauNom, string $classeNom, string $mode): array
    {
        $cycle = Cycle::firstOrCreate(
            ['code' => $cycleCode],
            ['nom' => $cycleNom, 'ordre' => 1, 'actif' => true]
        );

        OrganisationCycle::updateOrCreate(
            ['cycle_id' => $cycle->id],
            ['mode' => $mode]
        );

        $niveau = Niveau::firstOrCreate(
            ['code' => $cycleCode.'-N1'],
            ['nom' => $niveauNom, 'cycle_id' => $cycle->id, 'ordre' => 1]
        );

        $classe = Classe::firstOrCreate(
            ['code' => $cycleCode.'-C1'],
            ['nom' => $classeNom, 'niveau_id' => $niveau->id, 'annee_scolaire_id' => $this->annee->id, 'capacite_max' => 30, 'statut' => 'active']
        );

        AffectationPedagogiqueResource::context()->reset();

        return compact('cycle', 'niveau', 'classe');
    }

    private function creerEnseignant(string $matricule, string $nom, string $prenom): Enseignant
    {
        $employe = Employe::create([
            'matricule' => $matricule,
            'nom' => $nom,
            'prenom' => $prenom,
            'poste' => 'Enseignant',
            'date_embauche' => '2024-09-01',
            'statut' => 'actif',
        ]);

        return Enseignant::create([
            'employe_id' => $employe->id,
            'statut_contractuel' => 'titulaire',
        ]);
    }

    public function test_pc1_1_matieres_crud_et_nature(): void
    {
        $service = app(CatalogueMatiereService::class);

        // 1. Création avec nature et coefficient_defaut
        $matiere = $service->enregistrer([
            'nom' => 'Physique-Chimie',
            'nature' => 'matiere',
            'categorie_matiere_id' => $this->categorie->id,
            'coefficient_defaut' => 3.5,
            'actif' => true,
        ]);

        $this->assertDatabaseHas('matieres', [
            'id' => $matiere->id,
            'nom' => 'Physique-Chimie',
            'nature' => 'matiere',
            'coefficient_defaut' => 3.5,
        ]);

        // 2. Modification
        $service->enregistrer([
            'nom' => 'Sciences Physiques et Chimiques',
            'nature' => 'domaine',
            'categorie_matiere_id' => $this->categorie->id,
            'coefficient_defaut' => 4.0,
            'actif' => true,
        ], $matiere->id);

        $this->assertDatabaseHas('matieres', [
            'id' => $matiere->id,
            'nom' => 'Sciences Physiques et Chimiques',
            'nature' => 'domaine',
            'coefficient_defaut' => 4.0,
        ]);

        // 3. Filament MatiereResource
        Livewire::test(ListMatieres::class)
            ->assertCanSeeTableRecords([$matiere->fresh()]);

        Livewire::test(CreateMatiere::class)
            ->fillForm([
                'nom' => 'Arts Plastiques',
                'nature' => 'activite',
                'categorie_matiere_id' => $this->categorie->id,
                'coefficient_defaut' => 1.5,
                'actif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('matieres', [
            'nom' => 'Arts Plastiques',
            'nature' => 'activite',
        ]);
    }

    public function test_pc1_2_offres_pedagogiques_niveau_et_classe(): void
    {
        $service = app(OffrePedagogiqueService::class);
        $structure = $this->creerStructure('SEC', 'Secondaire', '3ème', '3ème A', 'matiere');

        $matiere = Matiere::create([
            'nom' => 'Histoire-Géographie',
            'nature' => 'matiere',
            'categorie_matiere_id' => $this->categorie->id,
            'coefficient_defaut' => 2.0,
            'actif' => true,
        ]);

        // 1. Offre de niveau (classe_id = null)
        $offreNiveau = $service->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $structure['niveau']->id,
            'classe_id' => null,
            'matiere_id' => $matiere->id,
            'coefficient_matiere' => 2.5,
            'heures_hebdomadaires' => 3.0,
            'actif' => true,
        ]);

        $this->assertDatabaseHas('offres_pedagogiques', [
            'id' => $offreNiveau->id,
            'classe_id' => null,
            'coefficient_matiere' => 2.5,
        ]);

        // 2. Offre de classe prioritaire (classe_id non null)
        $offreClasse = $service->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $structure['niveau']->id,
            'classe_id' => $structure['classe']->id,
            'matiere_id' => $matiere->id,
            'coefficient_matiere' => 3.0,
            'heures_hebdomadaires' => 4.0,
            'actif' => true,
        ]);

        $this->assertDatabaseHas('offres_pedagogiques', [
            'id' => $offreClasse->id,
            'classe_id' => $structure['classe']->id,
            'coefficient_matiere' => 3.0,
        ]);

        // Résolution effective : l'offre de classe prévaut sur l'offre de niveau
        $effective = $service->effective($this->annee->id, $structure['classe']->id, $matiere->id);
        $this->assertNotNull($effective);
        $this->assertSame($offreClasse->id, $effective->id);
        $this->assertSame('3.00', (string) $effective->coefficient_matiere);

        // 3. Doublon rejeté
        $this->expectException(ValidationException::class);
        $service->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $structure['niveau']->id,
            'classe_id' => $structure['classe']->id,
            'matiere_id' => $matiere->id,
            'coefficient_matiere' => 3.0,
        ]);
    }

    public function test_pc1_3_affectations_maternelle_primaire_secondaire_et_est_autorise(): void
    {
        $affectationService = app(AffectationPedagogiqueServiceInterface::class);
        $offreService = app(OffrePedagogiqueService::class);

        // --- MATERNELLE (mode classe) ---
        $mat = $this->creerStructure('MAT', 'Maternelle', 'Petite Section', 'PS A', 'classe');
        $ensMat = $this->creerEnseignant('ENS-MAT', 'Etoa', 'Marie');

        // Affectation Principal (pas d'offre obligatoire)
        $matiereEveil = Matiere::create([
            'nom' => 'Activités d\'éveil',
            'nature' => 'activite',
            'categorie_matiere_id' => $this->categorie->id,
            'actif' => true,
        ]);

        $offreService->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $mat['niveau']->id,
            'classe_id' => null,
            'matiere_id' => $matiereEveil->id,
            'coefficient_matiere' => 1.0,
            'actif' => true,
        ]);

        $affMat = $affectationService->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $mat['classe']->id,
            'enseignant_id' => $ensMat->id,
            'type' => 'principal',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'actif' => true,
        ]);

        $this->assertDatabaseHas('affectations_pedagogiques', [
            'id' => $affMat->id,
            'type' => 'principal',
            'matiere_id' => null,
            'offre_pedagogique_id' => null,
        ]);

        // --- PRIMAIRE (mode classe) avec assistant ---
        $pri = $this->creerStructure('PRI', 'Primaire', 'CM2', 'CM2 B', 'classe');
        $ensPriPrinc = $this->creerEnseignant('ENS-PRI-1', 'Abena', 'Jean');
        $ensPriAssis = $this->creerEnseignant('ENS-PRI-2', 'Mballa', 'Claire');

        $matiereAnglais = Matiere::create([
            'nom' => 'Anglais Primaire',
            'nature' => 'matiere',
            'categorie_matiere_id' => $this->categorie->id,
            'actif' => true,
        ]);

        $offreService->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $pri['niveau']->id,
            'classe_id' => null,
            'matiere_id' => $matiereAnglais->id,
            'coefficient_matiere' => 2.0,
            'actif' => true,
        ]);

        $affPriPrinc = $affectationService->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $pri['classe']->id,
            'enseignant_id' => $ensPriPrinc->id,
            'type' => 'principal',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'actif' => true,
        ]);

        $affPriAssis = $affectationService->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $pri['classe']->id,
            'enseignant_id' => $ensPriAssis->id,
            'type' => 'assistant',
            'matiere_ids' => [$matiereAnglais->id],
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'actif' => true,
        ]);

        $this->assertDatabaseHas('affectation_pedagogique_matieres', [
            'affectation_pedagogique_id' => $affPriAssis->id,
            'matiere_id' => $matiereAnglais->id,
        ]);

        // --- SECONDAIRE (mode matière, offre effective obligatoire) ---
        $sec = $this->creerStructure('SEC2', 'Secondaire', 'Terminale', 'Tle C', 'matiere');
        $ensSec = $this->creerEnseignant('ENS-SEC', 'Kuate', 'Dieudonné');

        $matiereMaths = Matiere::create([
            'nom' => 'Mathématiques Expertes',
            'nature' => 'matiere',
            'categorie_matiere_id' => $this->categorie->id,
            'actif' => true,
        ]);

        $offreSec = $offreService->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $sec['niveau']->id,
            'classe_id' => $sec['classe']->id,
            'matiere_id' => $matiereMaths->id,
            'coefficient_matiere' => 6.0,
            'actif' => true,
        ]);

        $affSec = $affectationService->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'classe_id' => $sec['classe']->id,
            'enseignant_id' => $ensSec->id,
            'type' => 'matiere',
            'matiere_id' => $matiereMaths->id,
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'actif' => true,
        ]);

        $this->assertDatabaseHas('affectations_pedagogiques', [
            'id' => $affSec->id,
            'type' => 'matiere',
            'matiere_id' => $matiereMaths->id,
            'offre_pedagogique_id' => $offreSec->id,
        ]);

        // --- VÉRIFICATION AUTORISATION PÉDAGOGIQUE (estAutorise) ---
        // 1. Enseignant Maternelle principal : autorisé sur une matière/activité offerte de sa classe
        $this->assertTrue(
            $affectationService->estAutorise(
                $ensMat->id,
                $mat['classe']->id,
                $matiereEveil->id,
                $this->annee->id,
                '2026-10-15'
            )
        );

        // Enseignant Maternelle : non autorisé sur une matière non offerte pour sa classe
        $this->assertFalse(
            $affectationService->estAutorise(
                $ensMat->id,
                $mat['classe']->id,
                $matiereMaths->id,
                $this->annee->id,
                '2026-10-15'
            )
        );

        // 2. Enseignant Secondaire matière : autorisé sur sa matière
        $this->assertTrue(
            $affectationService->estAutorise(
                $ensSec->id,
                $sec['classe']->id,
                $matiereMaths->id,
                $this->annee->id,
                '2026-10-15'
            )
        );

        // 3. Enseignant Secondaire non autorisé sur une autre matière
        $this->assertFalse(
            $affectationService->estAutorise(
                $ensSec->id,
                $sec['classe']->id,
                $matiereAnglais->id,
                $this->annee->id,
                '2026-10-15'
            )
        );

        // 4. Enseignant non affecté à la classe : refusé
        $this->assertFalse(
            $affectationService->estAutorise(
                $ensMat->id,
                $sec['classe']->id,
                $matiereMaths->id,
                $this->annee->id,
                '2026-10-15'
            )
        );

        // 5. Date en dehors de la période de validité : refusé
        $this->assertFalse(
            $affectationService->estAutorise(
                $ensSec->id,
                $sec['classe']->id,
                $matiereMaths->id,
                $this->annee->id,
                '2028-01-01'
            )
        );
    }

    public function test_pc1_4_filament_resources_et_methodes(): void
    {
        $structure = $this->creerStructure('COL', 'Collège', '6ème', '6ème 1', 'matiere');
        $this->creerEnseignant('ENS-FIL', 'Mvondo', 'Théodore');

        $matiere = Matiere::create([
            'nom' => 'Français 6ème',
            'nature' => 'matiere',
            'categorie_matiere_id' => $this->categorie->id,
            'actif' => true,
        ]);

        $offre = app(OffrePedagogiqueService::class)->enregistrer([
            'annee_scolaire_id' => $this->annee->id,
            'niveau_id' => $structure['niveau']->id,
            'classe_id' => $structure['classe']->id,
            'matiere_id' => $matiere->id,
            'coefficient_matiere' => 4.0,
            'actif' => true,
        ]);

        // Vérification des méthodes statiques exposées par AffectationPedagogiqueResource
        $classes = AffectationPedagogiqueResource::classesDisponibles($this->annee->id);
        $this->assertArrayHasKey($structure['classe']->id, $classes);

        $enseignants = AffectationPedagogiqueResource::enseignantsDisponibles();
        $this->assertNotEmpty($enseignants);

        $offres = AffectationPedagogiqueResource::offresDisponibles($structure['classe']->id);
        $this->assertArrayHasKey($offre->id, $offres);

        $matieres = AffectationPedagogiqueResource::matieresDisponibles($this->annee->id, $structure['classe']->id);
        $this->assertArrayHasKey($matiere->id, $matieres);

        // Navigation et accès aux pages Filament
        $this->get(MatiereResource::getUrl())->assertSuccessful();
        $this->get(OffrePedagogiqueResource::getUrl())->assertSuccessful();
        $this->get(AffectationPedagogiqueResource::getUrl())->assertSuccessful();
    }

    public function test_pc1_5_binding_canonique_vs_legacy(): void
    {
        $service = app(AffectationPedagogiqueServiceInterface::class);

        // L'implémentation résolue doit impérativement être AffectationPedagogiqueService
        $this->assertInstanceOf(AffectationPedagogiqueService::class, $service);
        $this->assertNotInstanceOf(AffectationService::class, $service);
    }
}
