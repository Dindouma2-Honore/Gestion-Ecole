<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Exceptions\ReunionDejaClotureeException;
use App\Modules\Socle\Filament\Resources\ReunionResource\Pages\CreateReunion;
use App\Modules\Socle\Filament\Resources\ReunionResource\Pages\EditReunion;
use App\Modules\Socle\Filament\Resources\ReunionResource\Pages\ListReunions;
use App\Modules\Socle\Filament\Resources\ReunionResource\RelationManagers\DecisionsRelationManager;
use App\Modules\Socle\Models\Reunion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class SocleA8Test extends TestCase
{
    use RefreshDatabase;

    private ReunionServiceContract $reunions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reunions = app(ReunionServiceContract::class);
    }

    public function test_planifier_reunion_requires_authentication(): void
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );
    }

    public function test_planifier_reunion_creates_participants_and_ordered_agenda(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $p1 = User::factory()->create(['statut' => 'actif']);
        $p2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);

        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil pédagogique', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [$p1->id, $p2->id],
            ['Revue des programmes', 'Budget matériel'],
        );

        $this->assertSame('planifiee', $reunion->statut);
        $this->assertSame($createur->id, $reunion->created_by);
        $this->assertSame(2, $reunion->participants()->count());
        $points = $reunion->ordreDuJour()->orderBy('ordre')->pluck('point')->all();
        $this->assertSame(['Revue des programmes', 'Budget matériel'], $points);
    }

    public function test_marquer_presence_updates_the_participant_only(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $p1 = User::factory()->create(['statut' => 'actif']);
        $p2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [$p1->id, $p2->id],
            [],
        );

        $this->reunions->marquerPresence($reunion->id, $p1->id, true);

        $this->assertTrue((bool) $reunion->participants()->where('user_id', $p1->id)->first()->present);
        $this->assertNull($reunion->participants()->where('user_id', $p2->id)->first()->present);
    }

    public function test_ajouter_decision_creates_a_linked_tache_and_exposes_its_statut(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );

        $decision = $this->reunions->ajouterDecision(
            $reunion->id,
            'Réviser les fiches de cours',
            $responsable->id,
            now()->addDays(7),
        );

        $this->assertNotNull($decision->tache_id);
        $this->assertSame('a_faire', $decision->fresh()->statut);
        $this->assertSame($responsable->id, $decision->tache->responsable_id);
    }

    public function test_ajouter_decision_is_refused_once_the_reunion_is_cloturee(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );
        $this->reunions->cloturerReunion($reunion->id, 'Réunion terminée.');

        $this->expectException(ReunionDejaClotureeException::class);
        $this->reunions->ajouterDecision($reunion->id, 'Trop tard', $responsable->id, now()->addDays(7));
    }

    public function test_cloturer_reunion_sets_statut_and_compte_rendu(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );

        $this->reunions->cloturerReunion($reunion->id, 'Compte rendu final.');

        $reunion->refresh();
        $this->assertSame('terminee', $reunion->statut);
        $this->assertSame('Compte rendu final.', $reunion->compte_rendu);
    }

    public function test_compte_rendu_cannot_be_edited_directly_through_the_filament_form(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );

        Livewire::test(EditReunion::class, ['record' => $reunion->getRouteKey()])
            ->assertFormFieldDoesNotExist('compte_rendu');
    }

    public function test_cloturer_table_action_transitions_and_then_hides_itself(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );

        Livewire::test(ListReunions::class)
            ->assertTableActionVisible('cloturer', $reunion)
            ->callTableAction('cloturer', $reunion, ['compte_rendu' => 'RAS, tout va bien.'])
            ->assertHasNoTableActionErrors();

        $reunion->refresh();
        $this->assertSame('terminee', $reunion->statut);
        $this->assertSame('RAS, tout va bien.', $reunion->compte_rendu);

        Livewire::test(ListReunions::class)
            ->assertTableActionHidden('cloturer', $reunion);
    }

    public function test_create_reunion_via_filament_persists_participants_and_agenda(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $p1 = User::factory()->create(['statut' => 'actif']);
        $p2 = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);

        Livewire::test(CreateReunion::class)
            ->fillForm([
                'titre' => 'Conseil de discipline',
                'type' => 'discipline',
                'date_heure' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'participant_ids' => [$p1->id, $p2->id],
                'ordre_du_jour' => [
                    ['point' => 'Cas signalé'],
                    ['point' => 'Décision'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $reunion = Reunion::where('titre', 'Conseil de discipline')->firstOrFail();
        $this->assertSame($createur->id, $reunion->created_by);
        $this->assertSame(2, $reunion->participants()->count());
        $this->assertSame(
            ['Cas signalé', 'Décision'],
            $reunion->ordreDuJour()->orderBy('ordre')->pluck('point')->all(),
        );
    }

    public function test_decisions_relation_manager_lists_and_creates_decisions(): void
    {
        $createur = User::factory()->create(['statut' => 'actif']);
        $responsable = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($createur);
        $reunion = $this->reunions->planifierReunion(
            ['titre' => 'Conseil', 'type' => 'pedagogique', 'date_heure' => now()->addDays(2)],
            [],
            [],
        );

        Livewire::test(DecisionsRelationManager::class, [
            'ownerRecord' => $reunion,
            'pageClass' => EditReunion::class,
        ])
            ->callTableAction('ajouterDecision', data: [
                'description' => 'Mettre à jour le règlement intérieur',
                'responsable_id' => $responsable->id,
                'echeance' => now()->addDays(10)->format('Y-m-d'),
            ])
            ->assertHasNoActionErrors();

        $reunion->refresh();
        $this->assertSame(1, $reunion->decisions()->count());
        $decision = $reunion->decisions()->first();
        $this->assertSame($responsable->id, $decision->responsable_id);
        $this->assertNotNull($decision->tache_id);
    }
}
