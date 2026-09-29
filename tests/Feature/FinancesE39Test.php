<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Finances\Contracts\FraisScolaireServiceContract;
use App\Modules\Finances\Exceptions\EleveIntrouvableException;
use App\Modules\Finances\Exceptions\RemiseSansMotifException;
use App\Modules\Finances\Filament\Pages\CreateFrais;
use App\Modules\Finances\Filament\Resources\GrilleFraisResource;
use App\Modules\Finances\Filament\Resources\RemiseExonerationResource;
use App\Modules\Finances\Filament\Resources\RemiseExonerationResource\Pages\CreateRemiseExoneration;
use App\Modules\Finances\Filament\Resources\RemiseExonerationResource\Pages\ListRemisesExonerations;
use App\Modules\Finances\Models\CatalogueFraisDivers;
use App\Modules\Finances\Models\FraisDiversEleve;
use App\Modules\Finances\Models\GrilleFrais;
use App\Modules\Finances\Models\GroupeFrais;
use App\Modules\Finances\Models\Paiement;
use App\Modules\Finances\Models\RemiseExoneration;
use App\Modules\Finances\Models\TypeFraisRecurrent;
use App\Modules\Scolarite\Contracts\EleveServiceInterface;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Niveau;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\Support\FakeEleveService;
use Tests\TestCase;

class FinancesE39Test extends TestCase
{
    use RefreshDatabase;

    private FraisScolaireServiceContract $frais;

    private Niveau $niveau;

    private AnneeScolaire $annee;

    private GroupeFrais $groupeScolarite;

    private GroupeFrais $groupeAutres;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->niveau = Niveau::create(['nom' => 'CM2', 'code' => 'CM2', 'ordre' => 6]);
        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-07-31',
            'statut' => 'active',
        ]);

        $this->app->instance(EleveServiceInterface::class, new FakeEleveService([
            1 => ['nom' => 'Doe', 'prenom' => 'Jane', 'classe_id' => 1, 'niveau_id' => $this->niveau->id],
        ]));

        $this->frais = app(FraisScolaireServiceContract::class);
        $this->groupeScolarite = GroupeFrais::query()->where('code', 'scolarite')->firstOrFail();
        $this->groupeAutres = GroupeFrais::query()->where('code', 'autres')->firstOrFail();
    }

    private function creerGrille(string $typeFrais, float $montant): GrilleFrais
    {
        $type = TypeFraisRecurrent::query()->create([
            'nom' => ucfirst($typeFrais),
            'groupe_frais_id' => in_array($typeFrais, ['inscription', 'scolarite'], true)
                ? $this->groupeScolarite->id
                : $this->groupeAutres->id,
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'montant' => $montant,
            'actif' => true,
        ]);

        return GrilleFrais::create([
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'type_frais_recurrent_id' => $type->id,
            'montant' => $montant,
        ]);
    }

    private function affecterFraisDivers(string $nom, float $montant, string $groupe = 'autres', string $statut = 'actif'): FraisDiversEleve
    {
        $catalogue = CatalogueFraisDivers::query()->create([
            'nom' => $nom,
            'categorie' => 'autre',
            'groupe_frais_id' => $groupe === 'scolarite' ? $this->groupeScolarite->id : $this->groupeAutres->id,
            'actif' => true,
        ]);

        return FraisDiversEleve::query()->create([
            'catalogue_frais_divers_id' => $catalogue->id,
            'eleve_id' => 1,
            'annee_scolaire_id' => $this->annee->id,
            'montant' => $montant,
            'statut' => $statut,
        ]);
    }

    public function test_total_always_equals_the_sum_of_both_groups_for_varied_combinations(): void
    {
        $this->creerGrille('scolarite', 100000);
        $this->creerGrille('transport', 30000);
        $this->affecterFraisDivers('Sortie pédagogique', 12500);

        foreach ([
            null,
            ['scolarite', 'remise_pourcentage', 10.0],
            ['transport', 'remise_montant', 5000.0],
        ] as $remise) {
            RemiseExoneration::query()->delete();
            if ($remise) {
                $auteur = User::factory()->create(['statut' => 'actif']);
                $this->actingAs($auteur);
                $this->frais->accorderRemise(1, $remise[0], $remise[1], $remise[2], 'Contrôle invariant');
            }

            $parGroupe = $this->frais->getMontantDuParGroupe(1, $this->annee->id);
            $this->assertSame(['scolarite', 'autres'], array_keys($parGroupe));
            $this->assertSame($this->frais->getMontantDu(1, $this->annee->id), round(array_sum($parGroupe), 2));
        }
    }

    public function test_cancelled_individual_fee_is_excluded_from_total_and_both_groups(): void
    {
        $this->creerGrille('scolarite', 100000);
        $this->affecterFraisDivers('Tenue', 25000, statut: 'annule');

        $this->assertSame(100000.0, $this->frais->getMontantDu(1, $this->annee->id));
        $this->assertSame(['scolarite' => 100000.0, 'autres' => 0.0], $this->frais->getMontantDuParGroupe(1, $this->annee->id));
    }

    public function test_targeted_discount_only_impacts_the_matching_group(): void
    {
        $this->creerGrille('scolarite', 100000);
        $this->creerGrille('transport', 30000);
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);
        $this->frais->accorderRemise(1, 'scolarite', 'remise_pourcentage', 20, 'Bourse ciblée');

        $this->assertSame(['scolarite' => 80000.0, 'autres' => 30000.0], $this->frais->getMontantDuParGroupe(1, $this->annee->id));
        $this->assertSame(110000.0, $this->frais->getMontantDu(1, $this->annee->id));
    }

    public function test_new_recurring_type_is_immediately_counted_and_similar_individual_fee_is_not_deduplicated(): void
    {
        $this->creerGrille('sortie pedagogique', 18000);
        $this->affecterFraisDivers('Sortie pedagogique', 7000);

        $this->assertSame(['scolarite' => 0.0, 'autres' => 25000.0], $this->frais->getMontantDuParGroupe(1, $this->annee->id));
        $this->assertSame(25000.0, $this->frais->getMontantDu(1, $this->annee->id));
        $individuels = $this->frais->getSituationParFrais(1, $this->annee->id);
        $this->assertSame(25000.0, round(collect($individuels)->sum('attendu'), 2));
        $this->assertTrue(collect($individuels)->contains(fn (array $frais): bool => $frais['libelle'] === 'Sortie pedagogique'));

        $encaisseur = User::factory()->create();
        Paiement::create(['eleve_id' => 1, 'annee_scolaire_id' => $this->annee->id, 'montant' => 7000, 'mode' => 'especes', 'numero_recu' => 'INV-FRAIS-1', 'statut' => 'valide', 'encaisse_par' => $encaisseur->id, 'rubrique' => 'autre']);
        $individuels = $this->frais->getSituationParFrais(1, $this->annee->id);
        $this->assertSame((float) Paiement::query()->where('statut', 'valide')->sum('montant'), round((float) collect($individuels)->sum('recu'), 2));
    }

    public function test_get_montant_du_sums_the_grille_without_remise(): void
    {
        $this->creerGrille('scolarite', 100000);
        $this->creerGrille('inscription', 20000);

        $this->assertSame(120000.0, $this->frais->getMontantDu(1, $this->annee->id));
    }

    public function test_get_montant_du_throws_when_eleve_is_unknown(): void
    {
        $this->creerGrille('scolarite', 100000);

        $this->expectException(EleveIntrouvableException::class);
        $this->frais->getMontantDu(999, $this->annee->id);
    }

    public function test_get_montant_du_applies_a_percentage_remise(): void
    {
        $this->creerGrille('scolarite', 100000);
        $auteur = User::factory()->create(['statut' => 'actif']);
        $auteur->assignRole('Fondateur');
        $this->actingAs($auteur);
        $this->frais->accorderRemise(1, 'scolarite', 'remise_pourcentage', 10.0, 'Fratrie');

        $this->assertSame(90000.0, $this->frais->getMontantDu(1, $this->annee->id));
    }

    public function test_get_montant_du_applies_a_fixed_amount_remise(): void
    {
        $this->creerGrille('scolarite', 100000);
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);
        $this->frais->accorderRemise(1, 'scolarite', 'remise_montant', 15000.0, 'Aide sociale');

        $this->assertSame(85000.0, $this->frais->getMontantDu(1, $this->annee->id));
    }

    public function test_get_montant_du_applies_a_total_exoneration(): void
    {
        $this->creerGrille('scolarite', 100000);
        $this->creerGrille('inscription', 20000);
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);
        $this->frais->accorderRemise(1, 'scolarite', 'exoneration_totale', 0.0, 'Bourse complète');

        $this->assertSame(20000.0, $this->frais->getMontantDu(1, $this->annee->id));
    }

    public function test_get_echeancier_only_returns_tranches_of_the_matching_grille(): void
    {
        $grille = $this->creerGrille('scolarite', 100000);
        $autreNiveau = Niveau::create(['nom' => 'CM1', 'code' => 'CM1', 'ordre' => 5]);
        $autreType = TypeFraisRecurrent::query()->create([
            'nom' => 'Scolarite',
            'groupe_frais_id' => $this->groupeScolarite->id,
            'niveau_id' => $autreNiveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'montant' => 90000,
            'actif' => true,
        ]);
        $autreGrille = GrilleFrais::create([
            'niveau_id' => $autreNiveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'type_frais_recurrent_id' => $autreType->id,
            'montant' => 90000,
        ]);
        $grille->echeancier()->create(['libelle' => 'Tranche 2', 'date_echeance' => '2027-01-10', 'montant' => 50000, 'ordre' => 2]);
        $grille->echeancier()->create(['libelle' => 'Tranche 1', 'date_echeance' => '2026-10-10', 'montant' => 50000, 'ordre' => 1]);
        $autreGrille->echeancier()->create(['libelle' => 'Tranche unique', 'date_echeance' => '2026-10-10', 'montant' => 90000, 'ordre' => 1]);

        $libelles = $this->frais->getEcheancier(1, $this->annee->id)->pluck('libelle')->all();

        $this->assertSame(['Tranche 1', 'Tranche 2'], $libelles);
    }

    public function test_accorder_remise_requires_a_motif(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);

        $this->expectException(RemiseSansMotifException::class);
        $this->frais->accorderRemise(1, 'scolarite', 'remise_montant', 5000, '   ');
    }

    public function test_accorder_remise_requires_authentication(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->frais->accorderRemise(1, 'scolarite', 'remise_montant', 5000, 'Motif valide');
    }

    public function test_accorder_remise_is_audited_with_the_current_annee_and_approbateur(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);

        $remise = $this->frais->accorderRemise(1, 'scolarite', 'remise_pourcentage', 20, 'Décision Fondateur');

        $this->assertSame($this->annee->id, $remise->annee_scolaire_id);
        $this->assertSame($auteur->id, $remise->approuve_par);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => RemiseExoneration::class,
            'subject_id' => $remise->id,
            'causer_id' => $auteur->id,
        ]);
    }

    public function test_get_grille_frais_filters_by_niveau_and_annee(): void
    {
        $this->creerGrille('scolarite', 100000);
        $autreAnnee = AnneeScolaire::create([
            'libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-31', 'statut' => 'cloturee',
        ]);
        GrilleFrais::create([
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $autreAnnee->id,
            'type_frais_recurrent_id' => TypeFraisRecurrent::query()->create([
                'nom' => 'Scolarite',
                'groupe_frais_id' => $this->groupeScolarite->id,
                'niveau_id' => $this->niveau->id,
                'annee_scolaire_id' => $autreAnnee->id,
                'montant' => 95000,
                'actif' => true,
            ])->id,
            'montant' => 95000,
        ]);

        $grilles = $this->frais->getGrilleFrais($this->niveau->id, $this->annee->id);

        $this->assertCount(1, $grilles);
        $this->assertSame(100000.0, (float) $grilles->first()->montant);
    }

    public function test_grille_frais_resource_lists_and_creates_via_filament(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $auteur->assignRole('Fondateur');
        $this->actingAs($auteur);

        Livewire::test(CreateFrais::class)
            ->fillForm([
                'nature' => 'recurrent',
                'groupe_frais_id' => $this->groupeAutres->id,
                'nom' => 'Cantine',
                'niveau_id' => $this->niveau->id,
                'annee_scolaire_id' => $this->annee->id,
                'montant' => 30000,
                'actif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('types_frais_recurrents', [
            'niveau_id' => $this->niveau->id,
            'annee_scolaire_id' => $this->annee->id,
            'nom' => 'Cantine',
            'groupe_frais_id' => $this->groupeAutres->id,
        ]);

        Livewire::test(CreateFrais::class)
            ->fillForm([
                'nature' => 'divers',
                'groupe_frais_id' => $this->groupeAutres->id,
                'nom' => 'Sortie culturelle',
                'categorie' => 'autre',
                'montant' => 12500,
                'actif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('catalogue_frais_divers', [
            'nom' => 'Sortie culturelle',
            'groupe_frais_id' => $this->groupeAutres->id,
            'montant_defaut' => 12500,
        ]);
        $this->assertSame(2, GroupeFrais::query()->count());
        $this->assertFalse(CreateFrais::shouldRegisterNavigation());

        $this->get(GrilleFraisResource::getUrl('index'))->assertSuccessful();
    }

    public function test_remise_exoneration_resource_creates_through_the_contract(): void
    {
        $auteur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($auteur);
        $this->creerGrille('scolarite', 100000);

        Livewire::test(CreateRemiseExoneration::class)
            ->fillForm([
                'eleve_id' => 1,
                'type_frais' => 'scolarite',
                'type' => 'remise_montant',
                'valeur' => 10000,
                'motif' => 'Difficulté financière ponctuelle',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('remises_exonerations', [
            'eleve_id' => 1,
            'approuve_par' => $auteur->id,
        ]);

        Livewire::test(ListRemisesExonerations::class)
            ->assertSee('Jane Doe');

        $auteur->assignRole('Fondateur');
        $this->get(RemiseExonerationResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('Accorder une remise ou exonération')
            ->assertSee('Retour aux élèves');
    }
}
