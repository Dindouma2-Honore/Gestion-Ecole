<?php

namespace Tests\Feature\Pedagogie;

use App\Models\User;
use App\Modules\Pedagogie\Contracts\AffectationPedagogiqueServiceInterface;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource;
use App\Modules\Pedagogie\Filament\Resources\AffectationPedagogiqueResource\Pages\ListAffectationsPedagogiques;
use App\Modules\Pedagogie\Filament\Resources\MatiereResource\Pages\ListMatieres;
use App\Modules\Pedagogie\Filament\Resources\OffrePedagogiqueResource\Pages\ListOffresPedagogiques;
use App\Modules\Pedagogie\Models\AffectationPedagogique;
use App\Modules\Pedagogie\Models\Matiere;
use App\Modules\Pedagogie\Models\OffrePedagogique;
use App\Modules\Pedagogie\Models\OrganisationCycle;
use App\Modules\Pedagogie\Services\AffectationPedagogiqueService;
use App\Modules\Pedagogie\Services\AffectationService;
use App\Modules\Pedagogie\Services\OffrePedagogiqueService;
use App\Modules\Pedagogie\Services\OrganisationCycleService;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrganisationPedagogiqueTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Classe $classe;

    private Niveau $niveau;

    private Cycle $cycle;

    private Enseignant $enseignant;

    private Matiere $matiere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);
        $this->annee = AnneeScolaire::create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31', 'statut' => 'active']);
        $this->cycle = Cycle::create(['code' => 'TEST-PRI', 'nom' => 'Primaire test', 'ordre' => 1]);
        $this->niveau = Niveau::create(['cycle_id' => $this->cycle->id, 'code' => 'CM1-TEST', 'nom' => 'CM1', 'ordre' => 1]);
        $this->classe = Classe::create(['nom' => 'CM1 A', 'niveau_id' => $this->niveau->id, 'annee_scolaire_id' => $this->annee->id, 'capacite_max' => 30, 'statut' => 'active']);
        OrganisationCycle::create(['cycle_id' => $this->cycle->id, 'mode' => 'classe']);
        $this->enseignant = $this->teacher('Marie');
        $this->matiere = Matiere::create(['nom' => 'Mathématiques', 'code' => 'MATH-T', 'coefficient' => 1, 'coefficient_defaut' => 1, 'actif' => true]);
        $this->offre();
    }

    private function teacher(string $nom): Enseignant
    {
        $e = Employe::create(['matricule' => 'EMP-'.$nom, 'nom' => $nom, 'prenom' => 'Test', 'poste' => 'Enseignant', 'departement' => 'Pédagogie', 'date_embauche' => '2026-09-01', 'statut' => 'actif']);

        return Enseignant::create(['employe_id' => $e->id, 'statut_contractuel' => 'titulaire']);
    }

    private function data(array $extra = []): array
    {
        return array_replace(['annee_scolaire_id' => $this->annee->id, 'classe_id' => $this->classe->id, 'enseignant_id' => $this->enseignant->id, 'type' => 'principal', 'actif' => true], $extra);
    }

    private function affect(array $extra = []): AffectationPedagogique
    {
        return app(AffectationPedagogiqueServiceInterface::class)->enregistrer($this->data($extra));
    }

    private function offre(array $extra = []): OffrePedagogique
    {
        return app(OffrePedagogiqueService::class)->enregistrer(array_replace(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->niveau->id, 'matiere_id' => $this->matiere->id, 'coefficient_matiere' => 2, 'actif' => true], $extra));
    }

    public function test_container_resolves_single_canonical_affectation_service(): void
    {
        $service = app(AffectationPedagogiqueServiceInterface::class);

        $this->assertInstanceOf(AffectationPedagogiqueService::class, $service);
        $this->assertInstanceOf(AffectationPedagogiqueServiceInterface::class, $service);
        $this->assertNotInstanceOf(AffectationService::class, $service);
        $this->assertTrue(is_callable([$service, 'enregistrer']));
        $this->assertTrue(is_callable([$service, 'estAutorise']));
    }

    public function test_principal_has_one_assignment_and_can_teach_class_subjects(): void
    {
        $a = $this->affect();
        $this->assertNull($a->matiere_id);
        $this->assertDatabaseCount('affectations_pedagogiques', 1);
        $this->assertTrue(app(AffectationPedagogiqueServiceInterface::class)->estAutorise($this->enseignant->id, $this->classe->id, $this->matiere->id, $this->annee->id, '2026-10-01'));
        $this->assertDatabaseHas('activity_log', ['subject_type' => AffectationPedagogique::class, 'subject_id' => $a->id]);
    }

    public function test_two_principals_cannot_overlap(): void
    {
        $this->affect();
        $other = $this->teacher('Paul');
        $this->expectException(ValidationException::class);
        $this->affect(['enseignant_id' => $other->id]);
    }

    public function test_successive_principals_keep_history_and_dates(): void
    {
        $this->affect(['date_fin' => '2026-12-31']);
        $second = $this->teacher('Paul');
        $this->affect(['enseignant_id' => $second->id, 'date_debut' => '2027-01-01']);
        $this->assertDatabaseCount('affectations_pedagogiques', 2);
        $service = app(AffectationPedagogiqueServiceInterface::class);
        $this->assertFalse($service->estAutorise($this->enseignant->id, $this->classe->id, $this->matiere->id, $this->annee->id, '2027-01-02'));
    }

    public function test_multiple_assistants_and_specialised_scope(): void
    {
        $this->affect();
        $specialist = $this->teacher('Anglais');
        $generalist = $this->teacher('General');
        $this->affect(['type' => 'assistant', 'enseignant_id' => $specialist->id, 'matiere_ids' => [$this->matiere->id]]);
        $this->affect(['type' => 'assistant', 'enseignant_id' => $generalist->id]);
        $other = Matiere::create(['nom' => 'Anglais', 'code' => 'ANG-T', 'coefficient' => 1, 'actif' => true]);
        $this->offre(['matiere_id' => $other->id]);
        $service = app(AffectationPedagogiqueServiceInterface::class);
        $this->assertTrue($service->estAutorise($specialist->id, $this->classe->id, $this->matiere->id, $this->annee->id, '2026-10-01'));
        $this->assertFalse($service->estAutorise($specialist->id, $this->classe->id, $other->id, $this->annee->id, '2026-10-01'));
        $this->assertTrue($service->estAutorise($generalist->id, $this->classe->id, $other->id, $this->annee->id, '2026-10-01'));
    }

    public function test_secondary_requires_subject_and_uses_effective_offer(): void
    {
        OrganisationCycle::where('cycle_id', $this->cycle->id)->update(['mode' => 'matiere']);
        $a = $this->affect(['type' => 'matiere', 'matiere_id' => $this->matiere->id]);
        $this->assertNotNull($a->offre_pedagogique_id);
        $this->expectException(ValidationException::class);
        $this->affect();
    }

    public function test_inactive_teacher_cannot_be_assigned(): void
    {
        $this->enseignant->employe->update(['statut' => 'inactif']);
        $this->expectException(ValidationException::class);
        $this->affect();
    }

    public function test_class_override_and_annual_coefficients_do_not_change_catalogue(): void
    {
        $override = $this->offre(['classe_id' => $this->classe->id, 'coefficient_matiere' => 4]);
        $this->assertSame($override->id, app(OffrePedagogiqueService::class)->effective($this->annee->id, $this->classe->id, $this->matiere->id)->id);
        $this->assertEquals(1, $this->matiere->fresh()->coefficient_defaut);
        $this->expectException(ValidationException::class);
        $this->offre();
    }

    public function test_same_subject_is_allowed_on_another_school_year(): void
    {
        $autreAnnee = AnneeScolaire::create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-31', 'statut' => 'brouillon']);
        $offre = $this->offre(['annee_scolaire_id' => $autreAnnee->id]);
        $this->assertEquals($autreAnnee->id, $offre->annee_scolaire_id);
        $this->assertSame(2, OffrePedagogique::where('matiere_id', $this->matiere->id)->count());
    }

    public function test_same_subject_is_allowed_on_another_class_of_the_level(): void
    {
        $autreClasse = Classe::create(['nom' => 'CM1 B', 'niveau_id' => $this->niveau->id, 'annee_scolaire_id' => $this->annee->id, 'capacite_max' => 30, 'statut' => 'active']);
        $this->offre(['classe_id' => $this->classe->id]);
        $this->offre(['classe_id' => $autreClasse->id]);
        $this->assertSame(3, OffrePedagogique::where('matiere_id', $this->matiere->id)->count());
    }

    public function test_duplicate_class_offer_is_refused(): void
    {
        $this->offre(['classe_id' => $this->classe->id]);
        $this->expectException(ValidationException::class);
        $this->offre(['classe_id' => $this->classe->id]);
    }

    public function test_catalogue_coefficient_change_keeps_existing_offer_value(): void
    {
        $offre = $this->offre(['classe_id' => $this->classe->id, 'coefficient_matiere' => 5]);
        $this->matiere->update(['coefficient_defaut' => 7]);
        $this->assertEquals(7, $this->matiere->fresh()->coefficient_defaut);
        $this->assertEquals(5, $offre->fresh()->coefficient_matiere);
        $effective = app(OffrePedagogiqueService::class)->effective($this->annee->id, $this->classe->id, $this->matiere->id);
        $this->assertSame($offre->id, $effective->id);
        $this->assertEquals(5, $effective->coefficient_matiere);
    }

    public function test_archived_year_refuses_offer_write(): void
    {
        $this->annee->update(['statut' => 'archivee']);
        $this->expectException(ValidationException::class);
        $this->offre(['classe_id' => $this->classe->id]);
    }

    public function test_read_only_teacher_cannot_write_through_service(): void
    {
        $u = User::factory()->create(['statut' => 'actif', 'niveau_id' => $this->niveau->id]);
        $u->assignRole('Enseignant');
        $this->actingAs($u);
        $this->expectException(HttpException::class);
        $this->affect();
    }

    public function test_archived_year_cannot_be_modified(): void
    {
        $this->annee->update(['statut' => 'archivee']);
        $this->expectException(ValidationException::class);
        $this->affect();
    }

    public function test_cycle_mode_is_locked_once_assignments_exist(): void
    {
        $this->affect();
        $this->expectException(ValidationException::class);
        app(OrganisationCycleService::class)->enregistrer([['cycle_id' => $this->cycle->id, 'mode' => 'matiere']]);
    }

    public function test_material_modal_preserves_category_and_nature(): void
    {
        Livewire::test(ListMatieres::class)->callAction('create', data: [
            'nom' => 'Expression orale', 'code' => 'ORALE', 'nature' => 'domaine', 'categorie_matiere_id' => $this->matiere->categorie_matiere_id, 'actif' => true,
        ])->assertHasNoActionErrors();
        $m = Matiere::where('code', 'ORALE')->firstOrFail();
        $this->assertSame('domaine', $m->nature);
        Livewire::test(ListMatieres::class)->callTableAction('edit', $m, data: ['nom' => 'Langage oral'])->assertHasNoTableActionErrors();
        $this->assertSame('Langage oral', $m->fresh()->nom);
    }

    public function test_assignment_modal_creates_and_edits_through_service(): void
    {
        Livewire::test(ListAffectationsPedagogiques::class)->callAction('create', data: $this->data())->assertHasNoActionErrors();
        $a = AffectationPedagogique::firstOrFail();
        Livewire::test(ListAffectationsPedagogiques::class)->assertCanSeeTableRecords([$a])->assertTableActionVisible('view', $a)
            ->callTableAction('edit', $a, data: ['actif' => false])->assertHasNoTableActionErrors();
        $this->assertFalse($a->fresh()->actif);
        $this->assertFalse(AffectationPedagogiqueResource::canDelete($a));
    }

    public function test_offer_modal_and_context_filters_render(): void
    {
        Livewire::test(ListOffresPedagogiques::class)->callAction('create', data: [
            'annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->niveau->id, 'classe_id' => $this->classe->id,
            'matiere_id' => $this->matiere->id, 'coefficient_matiere' => 3, 'actif' => true,
        ])->assertHasNoActionErrors();
        $this->assertDatabaseCount('offres_pedagogiques', 2);
        $this->get('/admin/pedagogie/enseignements')->assertSuccessful()->assertSee('Organisation pédagogique');
    }
}
