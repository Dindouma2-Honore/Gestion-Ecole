<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use App\Modules\Socle\Filament\Resources\CourrierResource\Pages\CreateCourrier;
use App\Modules\Socle\Filament\Resources\CourrierResource\Pages\EditCourrier;
use App\Modules\Socle\Filament\Resources\CourrierResource\Pages\ListCourriers;
use App\Modules\Socle\Models\Courrier;
use App\Modules\Socle\Models\FormatNumerotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocleA6Test extends TestCase
{
    use RefreshDatabase;

    private CourrierServiceContract $courriers;

    protected function setUp(): void
    {
        parent::setUp();
        FormatNumerotation::create([
            'type_document' => 'courrier',
            'format' => 'COUR-{{annee}}-{{seq:3}}',
            'prochain_numero' => 1,
        ]);
        $this->courriers = app(CourrierServiceContract::class);
    }

    public function test_enregistrer_courrier_entrant_generates_numero_and_initial_statut(): void
    {
        $courrier = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Demande d’inscription',
            'expediteur' => 'M. Dupont',
            'destinataire' => 'Secrétariat',
        ]);

        $this->assertSame('entrant', $courrier->type);
        $this->assertSame('recu', $courrier->statut);
        $this->assertNotEmpty($courrier->numero);
        $this->assertDatabaseHas('courriers', ['id' => $courrier->id, 'numero' => $courrier->numero]);
    }

    public function test_changer_statut_follows_workflow_and_records_history(): void
    {
        $acteur = User::factory()->create(['statut' => 'actif']);
        $courrier = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Objet',
            'expediteur' => 'Expéditeur',
            'destinataire' => 'Destinataire',
        ]);

        $courrier->changerStatut('affecte', $acteur, 'Affecté au service comptabilité');
        $courrier->changerStatut('en_traitement', $acteur);
        $courrier->changerStatut('repondu', $acteur);
        $courrier->changerStatut('archive', $acteur);

        $this->assertSame('archive', $courrier->fresh()->statut);
        $this->assertSame(4, $courrier->historiqueStatuts()->count());
        $this->assertSame(
            $acteur->id,
            $courrier->historiqueStatuts()->latest('id')->first()->changed_by,
        );
    }

    public function test_changer_statut_rejects_invalid_transition_without_side_effects(): void
    {
        $courrier = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Objet',
            'expediteur' => 'Expéditeur',
            'destinataire' => 'Destinataire',
        ]);

        $this->expectException(TransitionStatutInvalideException::class);

        try {
            $courrier->changerStatut('archive');
        } finally {
            $this->assertSame('recu', $courrier->fresh()->statut);
            $this->assertSame(0, $courrier->historiqueStatuts()->count());
        }
    }

    public function test_affecter_a_service_sets_service_and_transitions_to_affecte(): void
    {
        $courrier = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Objet',
            'expediteur' => 'Expéditeur',
            'destinataire' => 'Destinataire',
        ]);

        $this->courriers->affecterAService($courrier->id, 7);

        $courrier->refresh();
        $this->assertSame(7, $courrier->service_affecte_id);
        $this->assertSame('affecte', $courrier->statut);
    }

    public function test_get_courriers_en_retard_only_returns_overdue_unfinished_courriers(): void
    {
        $enRetard = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'En retard',
            'expediteur' => 'A',
            'destinataire' => 'B',
            'date_limite_reponse' => now()->subDay(),
        ]);
        $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Dans les temps',
            'expediteur' => 'A',
            'destinataire' => 'B',
            'date_limite_reponse' => now()->addDay(),
        ]);
        $archiveEnRetard = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Archivé mais en retard',
            'expediteur' => 'A',
            'destinataire' => 'B',
            'date_limite_reponse' => now()->subDay(),
        ]);
        $archiveEnRetard->update(['statut' => 'repondu']);
        $archiveEnRetard->changerStatut('archive');

        $enRetardIds = $this->courriers->getCourriersEnRetard()->pluck('id');

        $this->assertTrue($enRetardIds->contains($enRetard->id));
        $this->assertFalse($enRetardIds->contains($archiveEnRetard->id));
    }

    public function test_statut_cannot_be_edited_directly_through_the_filament_form(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $courrier = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Objet',
            'expediteur' => 'Expéditeur',
            'destinataire' => 'Destinataire',
        ]);

        Livewire::test(CreateCourrier::class)
            ->assertFormFieldDoesNotExist('statut');

        Livewire::test(EditCourrier::class, ['record' => $courrier->getRouteKey()])
            ->assertFormFieldDoesNotExist('statut');
    }

    public function test_table_actions_enforce_the_workflow_and_write_history(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);
        $courrier = $this->courriers->enregistrerCourrierEntrant([
            'objet' => 'Objet',
            'expediteur' => 'Expéditeur',
            'destinataire' => 'Destinataire',
        ]);

        Livewire::test(ListCourriers::class)
            ->assertTableActionVisible('affecter', $courrier)
            ->assertTableActionHidden('marquer_en_traitement', $courrier)
            ->callTableAction('affecter', $courrier, ['service_affecte_id' => 3])
            ->assertHasNoTableActionErrors();

        $courrier->refresh();
        $this->assertSame('affecte', $courrier->statut);
        $this->assertSame(3, $courrier->service_affecte_id);
        $this->assertSame(1, $courrier->historiqueStatuts()->count());

        Livewire::test(ListCourriers::class)
            ->assertTableActionHidden('affecter', $courrier)
            ->assertTableActionVisible('marquer_en_traitement', $courrier)
            ->callTableAction('marquer_en_traitement', $courrier)
            ->assertHasNoTableActionErrors();

        $courrier->refresh();
        $this->assertSame('en_traitement', $courrier->statut);
        $this->assertSame(2, $courrier->historiqueStatuts()->count());
    }
}
