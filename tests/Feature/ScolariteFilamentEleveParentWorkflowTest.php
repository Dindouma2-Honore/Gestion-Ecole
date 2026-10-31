<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Scolarite\Filament\Resources\EleveResource;
use App\Modules\Scolarite\Filament\Resources\EleveResource\Pages\ListEleves;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource;
use App\Modules\Scolarite\Filament\Resources\ParentTuteurResource\Pages\ListParentTuteurs;
use App\Modules\Scolarite\Models\Eleve;
use App\Modules\Scolarite\Models\ParentTuteur;
use App\Modules\Socle\Models\AnneeScolaire;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScolariteFilamentEleveParentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AnneeScolaire $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['statut' => 'actif']);
        $this->admin->assignRole('Fondateur');
        $this->actingAs($this->admin);

        $this->annee = AnneeScolaire::create([
            'libelle' => '2026-2027',
            'date_debut' => '2026-09-01',
            'date_fin' => '2027-06-30',
            'statut' => 'active',
        ]);
    }

    public function test_creation_eleve_depuis_la_modale_filament(): void
    {
        Livewire::test(ListEleves::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view');

        Livewire::test(ListEleves::class)
            ->callAction('create', [
                'nom' => 'Ngono',
                'prenom' => 'Berthe',
                'sexe' => 'F',
                'statut' => 'admis',
                'nom_tuteur' => 'Ngono Paul',
                'telephone_tuteur' => '677000111',
            ]);

        $eleve = Eleve::where('nom', 'Ngono')->where('prenom', 'Berthe')->firstOrFail();
        $this->assertSame('F', $eleve->sexe);
        $this->assertSame('admis', $eleve->statut);
        $this->assertSame('Ngono Paul', $eleve->nom_tuteur);
        $this->assertSame('677000111', $eleve->telephone_tuteur);

        $this->get(EleveResource::getUrl('index'))->assertSuccessful();
        $this->get(EleveResource::getUrl('create'))->assertSuccessful();
    }

    public function test_modification_eleve_cree_et_synchronise_le_parent_tuteur(): void
    {
        $eleve = Eleve::create([
            'nom' => 'Tchoumi',
            'prenom' => 'Armand',
            'sexe' => 'M',
            'statut' => 'admis',
        ]);

        $this->assertNull($eleve->parentsTuteurs()->first());

        Livewire::test(ListEleves::class)
            ->callTableAction('edit', $eleve, [
                'nom' => 'Tchoumi',
                'prenom' => 'Armand',
                'sexe' => 'M',
                'statut' => 'admis',
                'nom_tuteur' => 'Tchoumi Jean',
                'telephone_tuteur' => '699111222',
            ]);

        $eleveFresh = $eleve->fresh();
        $this->assertSame('Tchoumi Jean', $eleveFresh->nom_tuteur);
        $this->assertSame('699111222', $eleveFresh->telephone_tuteur);

        $parent = ParentTuteur::where('nom', 'Tchoumi Jean')->firstOrFail();
        $this->assertSame('699111222', $parent->telephone);

        $this->assertTrue(
            $eleveFresh->parentsTuteurs()->where('parents_tuteurs.id', $parent->id)->exists(),
            'Le parent créé depuis la fiche élève n’est pas rattaché à l’élève.',
        );
        $this->assertTrue(
            (bool) $eleveFresh->parentsTuteurs()
                ->wherePivot('parent_id', $parent->id)
                ->wherePivot('responsable_paiement', true)
                ->exists(),
            'Le parent n’est pas marqué responsable de paiement.',
        );

        $this->get(EleveResource::getUrl('edit', ['record' => $eleve]))->assertSuccessful();
    }

    public function test_creation_et_modification_parent_tuteur_depuis_la_modale(): void
    {
        Livewire::test(ListParentTuteurs::class)
            ->assertSuccessful()
            ->assertActionExists('create')
            ->assertTableActionExists('edit')
            ->assertTableActionExists('view');

        Livewire::test(ListParentTuteurs::class)
            ->callAction('create', [
                'nom' => 'Essomba',
                'prenom' => 'Clarisse',
                'profession' => 'Commerçante',
                'telephone' => '655444333',
                'email' => 'clarisse.essomba@example.com',
                'portail_actif' => true,
            ]);

        $parent = ParentTuteur::where('nom', 'Essomba')->firstOrFail();
        $this->assertSame('655444333', $parent->telephone);
        $this->assertSame('clarisse.essomba@example.com', $parent->email);
        $this->assertTrue((bool) $parent->portail_actif);

        Livewire::test(ListParentTuteurs::class)
            ->callTableAction('edit', $parent, [
                'nom' => 'Essomba',
                'prenom' => 'Clarisse-Marie',
                'profession' => 'Entrepreneure',
                'telephone' => '655444334',
                'email' => 'clarisse.marie@example.com',
                'portail_actif' => true,
            ]);

        $parentFresh = $parent->fresh();
        $this->assertSame('Clarisse-Marie', $parentFresh->prenom);
        $this->assertSame('655444334', $parentFresh->telephone);

        $this->get(ParentTuteurResource::getUrl('index'))->assertSuccessful();
        $this->get(ParentTuteurResource::getUrl('create'))->assertSuccessful();
        $this->get(ParentTuteurResource::getUrl('edit', ['record' => $parent]))->assertSuccessful();
    }

    public function test_rattachement_eleve_parent_existante_est_reutilisee(): void
    {
        $parent = ParentTuteur::create([
            'nom' => 'Mvondo',
            'prenom' => 'Serge',
            'telephone' => '644555666',
        ]);

        $eleve = Eleve::create([
            'nom' => 'Mvondo',
            'prenom' => 'Léa',
            'sexe' => 'F',
            'statut' => 'admis',
            'nom_tuteur' => 'Mvondo Serge',
            'telephone_tuteur' => '644555666',
        ]);

        Livewire::test(ListEleves::class)
            ->callTableAction('edit', $eleve, [
                'nom' => 'Mvondo',
                'prenom' => 'Léa',
                'sexe' => 'F',
                'statut' => 'admis',
                'nom_tuteur' => 'Mvondo Serge',
                'telephone_tuteur' => '644555666',
            ]);

        $this->assertSame(1, ParentTuteur::where('nom', 'Mvondo')->count(), 'Un doublon de parent a été créé.');

        $eleveFresh = $eleve->fresh();
        $this->assertTrue(
            $eleveFresh->parentsTuteurs()->where('parents_tuteurs.id', $parent->id)->exists(),
            'L’élève n’est pas rattaché au parent existant.',
        );
    }

    public function test_suppression_parent_bloquee_tant_qu_il_a_des_eleves(): void
    {
        $parent = ParentTuteur::create(['nom' => 'Atangana', 'prenom' => 'Pierre', 'telephone' => '633222111']);
        $eleve = Eleve::create([
            'nom' => 'Atangana',
            'prenom' => 'Junior',
            'sexe' => 'M',
            'statut' => 'admis',
        ]);
        $eleve->parentsTuteurs()->attach($parent->id, [
            'lien' => 'pere',
            'responsable_legal' => true,
            'responsable_paiement' => true,
            'autorise_recuperation' => true,
        ]);

        Livewire::test(ListParentTuteurs::class)
            ->callTableAction('delete', $parent);

        $this->assertDatabaseHas('parents_tuteurs', ['id' => $parent->id]);
        $this->assertSame(1, ParentTuteur::count());
    }
}
