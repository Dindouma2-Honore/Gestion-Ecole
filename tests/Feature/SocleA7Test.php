<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\TacheServiceContract;
use App\Modules\Socle\Exceptions\CircuitValidationIncompletException;
use App\Modules\Socle\Exceptions\ValidateurNonAutoriseException;
use App\Modules\Socle\Filament\Resources\TacheResource\Pages\CreateTache;
use App\Modules\Socle\Filament\Resources\TacheResource\Pages\EditTache;
use App\Modules\Socle\Filament\Resources\TacheResource\Pages\ListTaches;
use App\Modules\Socle\Models\TacheValidation;
use App\Modules\Socle\Notifications\TacheEnRetard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class SocleA7Test extends TestCase
{
    use RefreshDatabase;

    private TacheServiceContract $taches;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taches = app(TacheServiceContract::class);
    }

    public function test_creer_tache_requires_authentication_and_supports_optional_taskable(): void
    {
        $responsable = User::factory()->create(['statut' => 'actif']);

        $this->expectException(AccessDeniedHttpException::class);
        $this->taches->creerTache('Nettoyer la cour', $responsable->id, now()->addDays(3));
    }

    public function test_creer_tache_records_creator_and_optional_taskable(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);

        $libre = $this->taches->creerTache('Nettoyer la cour', $responsable->id, now()->addDays(3));
        $liee = $this->taches->creerTache('Suivre un dossier', $responsable->id, now()->addDays(3), $responsable);

        $this->assertSame($createur->id, $libre->createur_id);
        $this->assertSame('a_faire', $libre->statut);
        $this->assertNull($libre->taskable_type);
        $this->assertSame($responsable->getMorphClass(), $liee->taskable_type);
        $this->assertSame($responsable->id, $liee->taskable_id);
    }

    public function test_demarrer_circuit_validation_requires_at_least_one_validateur(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));

        $this->expectException(CircuitValidationIncompletException::class);
        $this->taches->demarrerCircuitValidation($tache->id, []);
    }

    public function test_demarrer_circuit_validation_creates_ordered_steps_and_transitions_tache(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $validateur1 = User::factory()->create(['statut' => 'actif']);
        $validateur2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));

        $this->taches->demarrerCircuitValidation($tache->id, [$validateur1->id, $validateur2->id]);

        $this->assertSame('en_attente_validation', $tache->fresh()->statut);
        $etapes = TacheValidation::where('tache_id', $tache->id)->orderBy('niveau_validation')->get();
        $this->assertSame(1, $etapes[0]->niveau_validation);
        $this->assertSame($validateur1->id, $etapes[0]->validateur_id);
        $this->assertSame(2, $etapes[1]->niveau_validation);
        $this->assertSame($validateur2->id, $etapes[1]->validateur_id);
    }

    public function test_valider_etape_enforces_order_and_completes_circuit_only_once_all_levels_validate(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $validateur1 = User::factory()->create(['statut' => 'actif']);
        $validateur2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));
        $this->taches->demarrerCircuitValidation($tache->id, [$validateur1->id, $validateur2->id]);

        $this->expectException(ValidateurNonAutoriseException::class);
        $this->taches->validerEtape($tache->id, $validateur2->id, true);
    }

    public function test_valider_etape_advances_through_levels_and_marks_tache_validee(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $validateur1 = User::factory()->create(['statut' => 'actif']);
        $validateur2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));
        $this->taches->demarrerCircuitValidation($tache->id, [$validateur1->id, $validateur2->id]);

        $this->taches->validerEtape($tache->id, $validateur1->id, true);
        $this->assertSame('en_attente_validation', $tache->fresh()->statut);

        $this->taches->validerEtape($tache->id, $validateur2->id, true);
        $this->assertSame('validee', $tache->fresh()->statut);
    }

    public function test_valider_etape_rejection_stops_the_circuit_without_touching_later_steps(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $validateur1 = User::factory()->create(['statut' => 'actif']);
        $validateur2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));
        $this->taches->demarrerCircuitValidation($tache->id, [$validateur1->id, $validateur2->id]);

        $this->taches->validerEtape($tache->id, $validateur1->id, false, 'Incomplet');

        $this->assertSame('rejetee', $tache->fresh()->statut);
        $etape2 = TacheValidation::where('tache_id', $tache->id)->where('niveau_validation', 2)->first();
        $this->assertSame('en_attente', $etape2->statut);
    }

    public function test_get_taches_en_retard_excludes_validee_and_cloturee(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);

        $enRetard = $this->taches->creerTache('En retard', $responsable->id, now()->subDay());
        $cloturee = $this->taches->creerTache('Clôturée mais en retard', $responsable->id, now()->subDay());
        $cloturee->update(['statut' => 'validee']);
        $cloturee->changerStatut('cloturee');
        $this->taches->creerTache('Dans les temps', $responsable->id, now()->addDay());

        $ids = $this->taches->getTachesEnRetard()->pluck('id');

        $this->assertTrue($ids->contains($enRetard->id));
        $this->assertFalse($ids->contains($cloturee->id));
    }

    public function test_relancer_notifies_responsable_of_overdue_unfinished_taches_only(): void
    {
        Notification::fake();
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);

        $enRetard = $this->taches->creerTache('En retard', $responsable->id, now()->subDay());
        $cloturee = $this->taches->creerTache('Clôturée', $responsable->id, now()->subDay());
        $cloturee->update(['statut' => 'validee']);
        $cloturee->changerStatut('cloturee');

        $this->artisan('taches:relancer')->assertSuccessful();

        Notification::assertSentTo($responsable, TacheEnRetard::class, fn (TacheEnRetard $n): bool => true);
        Notification::assertSentToTimes($responsable, TacheEnRetard::class, 1);
    }

    public function test_statut_cannot_be_edited_directly_through_the_filament_form(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));

        Livewire::test(CreateTache::class)->assertFormFieldDoesNotExist('statut');
        Livewire::test(EditTache::class, ['record' => $tache->getRouteKey()])
            ->assertFormFieldDoesNotExist('statut');
    }

    public function test_record_actions_enforce_the_workflow_and_validation_circuit(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $validateur1 = User::factory()->create(['statut' => 'actif']);
        $validateur2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));

        Livewire::test(ListTaches::class)
            ->assertTableActionVisible('demarrer', $tache)
            ->assertTableActionHidden('soumettre_validation', $tache)
            ->callTableAction('demarrer', $tache)
            ->assertHasNoTableActionErrors();

        $tache->refresh();
        $this->assertSame('en_cours', $tache->statut);
        $this->assertSame(1, $tache->historiqueStatuts()->count());

        Livewire::test(ListTaches::class)
            ->assertTableActionVisible('soumettre_validation', $tache)
            ->callTableAction('soumettre_validation', $tache, [
                'validateur_ids' => [$validateur1->id, $validateur2->id],
            ])
            ->assertHasNoTableActionErrors();

        $tache->refresh();
        $this->assertSame('en_attente_validation', $tache->statut);
        $this->assertSame(2, $tache->validations()->count());

        $this->actingAs($validateur2);
        Livewire::test(ListTaches::class)
            ->assertTableActionHidden('valider_etape', $tache);

        $this->actingAs($validateur1);
        Livewire::test(ListTaches::class)
            ->assertTableActionVisible('valider_etape', $tache)
            ->callTableAction('valider_etape', $tache, ['commentaire' => 'RAS'])
            ->assertHasNoTableActionErrors();

        $tache->refresh();
        $this->assertSame('en_attente_validation', $tache->statut);

        $this->actingAs($validateur2);
        Livewire::test(ListTaches::class)
            ->assertTableActionVisible('valider_etape', $tache)
            ->callTableAction('valider_etape', $tache)
            ->assertHasNoTableActionErrors();

        $tache->refresh();
        $this->assertSame('validee', $tache->statut);

        Livewire::test(ListTaches::class)
            ->assertTableActionVisible('cloturer', $tache)
            ->callTableAction('cloturer', $tache)
            ->assertHasNoTableActionErrors();

        $this->assertSame('cloturee', $tache->fresh()->statut);
    }

    public function test_reject_then_resume_via_filament_actions(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $validateur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $tache = $this->taches->creerTache('Titre', $responsable->id, now()->addDays(3));
        $tache->changerStatut('en_cours', $createur);
        $this->taches->demarrerCircuitValidation($tache->id, [$validateur->id]);

        $this->actingAs($validateur);
        Livewire::test(ListTaches::class)
            ->callTableAction('rejeter_etape', $tache, ['commentaire' => 'À revoir'])
            ->assertHasNoTableActionErrors();

        $tache->refresh();
        $this->assertSame('rejetee', $tache->statut);

        $this->actingAs($createur);
        Livewire::test(ListTaches::class)
            ->assertTableActionVisible('reprendre', $tache)
            ->callTableAction('reprendre', $tache)
            ->assertHasNoTableActionErrors();

        $this->assertSame('en_cours', $tache->fresh()->statut);
    }
}
