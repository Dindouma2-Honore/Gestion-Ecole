<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Scolarite\Contracts\InscriptionServiceInterface;
use App\Modules\Scolarite\Models\Classe;
use App\Modules\Scolarite\Models\Inscription;
use App\Modules\Socle\Contracts\AnneeScolaireServiceContract;
use App\Modules\Socle\Exceptions\AnneeVerrouilleeException;
use App\Modules\Socle\Exceptions\TransitionStatutInvalideException;
use App\Modules\Socle\Models\AnneeScolaire;
use App\Modules\Socle\Models\Periode;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SocleA3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_activation_closes_previous_year_and_keeps_only_one_active(): void
    {
        $ancienne = $this->annee('2025-2026', AnneeScolaire::STATUT_ACTIVE);
        $nouvelle = $this->annee('2026-2027');
        Cache::put('annee_scolaire_courante_id_v2', $ancienne->id);

        $service = app(AnneeScolaireServiceContract::class);
        $service->activerAnnee($nouvelle->id);

        $this->assertSame(AnneeScolaire::STATUT_CLOTUREE, $ancienne->fresh()->statut);
        $this->assertSame(AnneeScolaire::STATUT_ACTIVE, $nouvelle->fresh()->statut);
        $this->assertSame(1, AnneeScolaire::where('statut', 'active')->count());
        $this->assertSame($nouvelle->id, $service->getAnneeCouranteId());
        $this->assertIsInt(Cache::get('annee_scolaire_courante_id_v2'));
    }

    public function test_status_cycle_cannot_be_bypassed(): void
    {
        $annee = $this->annee('2026-2027');
        $service = app(AnneeScolaireServiceContract::class);

        $this->expectException(TransitionStatutInvalideException::class);
        $service->archiverAnnee($annee->id);
    }

    public function test_closed_year_is_editable_only_by_founder_and_archived_year_by_nobody(): void
    {
        $fondateur = User::factory()->create();
        $fondateur->assignRole('Fondateur');
        $enseignant = User::factory()->create();
        $enseignant->assignRole('Enseignant');
        $annee = $this->annee('2026-2027', AnneeScolaire::STATUT_CLOTUREE);
        $service = app(AnneeScolaireServiceContract::class);

        $this->assertTrue($service->ecritureAutorisee($annee->id, $fondateur));
        $this->assertFalse($service->ecritureAutorisee($annee->id, $enseignant));

        $annee->update(['statut' => AnneeScolaire::STATUT_ARCHIVEE]);
        $this->assertFalse($service->ecritureAutorisee($annee->id, $fondateur));
        $this->assertFalse(Gate::forUser($fondateur)->allows('update', $annee->fresh()));
    }

    public function test_periods_are_sorted_and_archived_year_is_locked(): void
    {
        $annee = $this->annee('2026-2027', AnneeScolaire::STATUT_ACTIVE);
        $periodeDeux = $this->periode($annee, 'Trimestre 2', 2);
        $periodeUn = $this->periode($annee, 'Trimestre 1', 1);

        $periodes = app(AnneeScolaireServiceContract::class)->getPeriodes($annee->id);
        $this->assertSame([$periodeUn->id, $periodeDeux->id], array_column($periodes, 'id'));

        $annee->update(['statut' => AnneeScolaire::STATUT_ARCHIVEE]);
        $this->expectException(AnneeVerrouilleeException::class);
        $periodeUn->update(['nom' => 'Modification interdite']);
    }

    public function test_school_periods_are_common_and_readable_by_every_authenticated_profile(): void
    {
        $annee = $this->annee('2026-2027', AnneeScolaire::STATUT_ACTIVE);
        Periode::create([
            'annee_scolaire_id' => $annee->id,
            'libelle' => 'Premier trimestre',
            'type' => 'trimestre',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-12-18',
            'ordre' => 1,
        ]);

        $this->get('/periodes-scolaires')->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create(['statut' => 'actif']))
            ->get('/periodes-scolaires')
            ->assertSuccessful()
            ->assertSee('Premier trimestre')
            ->assertSee('Calendrier commun aux trois niveaux');

        $this->assertFalse(Schema::hasColumn('periodes', 'niveau_id'));
    }

    /**
     * Remplace l'ancien test_transfer_fails_explicitly_until_scolarite_implements_the_port :
     * le port (TransfertElevesAnneeContract) est désormais réellement
     * implémenté par TransfertAnneeService (Scolarité), voir
     * ScolariteServiceProvider::register(). Un élève encore activement
     * inscrit (statut `active`) doit être réinscrit dans la classe de même
     * nom de l'année destination, via le circuit normal de réinscription
     * (nouvelle inscription `en_attente_versement`, jamais `active`
     * automatiquement — la confirmation du versement reste une étape
     * distincte).
     *
     * L'année source est créée ACTIVE (pas clôturée directement) : toutes
     * les écritures (classe, inscription, activation) ont lieu pendant
     * qu'elle est active, pour ne pas déclencher VerrouAnneeObserver. Elle
     * n'est clôturée qu'ensuite, via activerAnnee() sur la destination —
     * même mécanisme que test_activation_closes_previous_year_and_keeps_only_one_active.
     */
    public function test_transfer_reinscribes_active_students_into_the_matching_class_in_the_destination_year(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->actingAs($fondateur);

        $source = $this->annee('2025-2026', AnneeScolaire::STATUT_ACTIVE);

        $classeSource = Classe::create([
            'nom' => 'CP1 A',
            'niveau_id' => 1,
            'annee_scolaire_id' => $source->id,
            'capacite_max' => 30,
        ]);

        $inscriptionSourceId = app(InscriptionServiceInterface::class)->preparerEtInscrire(
            null,
            ['nom' => 'Doe', 'prenom' => 'Jane'],
            null,
            ['nom' => 'Doe', 'prenom' => 'Robert', 'telephone' => '690000000', 'email' => 'robert@example.test'],
            'parent',
            $classeSource->id,
            $source->id,
        );
        app(InscriptionServiceInterface::class)->activerApresVersement($inscriptionSourceId);
        $eleveId = Inscription::findOrFail($inscriptionSourceId)->eleve_id;

        $destination = $this->annee('2026-2027');
        app(AnneeScolaireServiceContract::class)->activerAnnee($destination->id);

        Classe::create([
            'nom' => 'CP1 A',
            'niveau_id' => 1,
            'annee_scolaire_id' => $destination->id,
            'capacite_max' => 30,
        ]);

        app(AnneeScolaireServiceContract::class)->transfererEleves($source->id, $destination->id);

        $this->assertDatabaseHas('inscriptions', [
            'eleve_id' => $eleveId,
            'annee_scolaire_id' => $destination->id,
            'type' => 'reinscription',
            'statut' => 'en_attente_versement',
        ]);
    }

    public function test_founder_can_open_a3_screens_and_sees_active_year_banner(): void
    {
        $fondateur = User::factory()->create(['statut' => 'actif']);
        $fondateur->assignRole('Fondateur');
        $this->annee('2026-2027', AnneeScolaire::STATUT_ACTIVE);
        $this->actingAs($fondateur);

        $this->get('/admin/annee-scolaires')->assertSuccessful()->assertSee('Année scolaire active')->assertSee('2026-2027');
        $this->get('/admin/periodes')->assertSuccessful();
    }

    private function annee(string $libelle, string $statut = AnneeScolaire::STATUT_BROUILLON): AnneeScolaire
    {
        $debut = (int) substr($libelle, 0, 4);

        return AnneeScolaire::create([
            'libelle' => $libelle,
            'date_debut' => $debut.'-09-01',
            'date_fin' => ($debut + 1).'-07-31',
            'statut' => $statut,
        ]);
    }

    private function periode(AnneeScolaire $annee, string $nom, int $ordre): Periode
    {
        return Periode::create([
            'annee_scolaire_id' => $annee->id,
            'libelle' => $nom,
            'type' => 'trimestre',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-12-15',
            'ordre' => $ordre,
        ]);
    }
}
