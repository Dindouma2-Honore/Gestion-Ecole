<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Contracts\CourrierServiceContract;
use App\Modules\Socle\Models\CourrierModele;
use App\Modules\Socle\Models\FormatNumerotation;
use App\Modules\Socle\Models\Groupe;
use App\Modules\Scolarite\Models\ParentTuteur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourrierDestinatairesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        FormatNumerotation::create([
            'type_document' => 'courrier',
            'format' => 'COUR-{ANNEE}-{SEQ:5}',
            'prochain_numero' => 1,
        ]);
    }

    public function test_selected_parent_is_materialized_and_history_does_not_change(): void
    {
        $parent = ParentTuteur::create([
            'nom' => 'Nkoa', 'prenom' => 'Marie', 'telephone' => '690000001',
            'email' => 'marie@example.test', 'portail_actif' => true,
        ]);

        $courrier = app(CourrierServiceContract::class)->preparerCourrierSortant([
            'objet' => 'Réunion', 'expediteur' => 'Direction', 'contenu' => 'Bonjour parent',
            'cible_type' => 'parents_selectionnes', 'cible_config' => ['parent_ids' => [$parent->id]],
            'canal' => 'email',
        ]);

        $this->assertCount(1, $courrier->destinataires);
        $this->assertSame('marie@example.test', $courrier->destinataires->first()->email);

        $parent->update(['email' => 'nouveau@example.test']);
        $this->assertSame('marie@example.test', $courrier->destinataires()->first()->email);
    }

    public function test_permanent_group_is_optional_and_resolved_to_concrete_users(): void
    {
        $groupe = Groupe::create(['nom' => 'Équipe pédagogique']);
        $membre = User::factory()->create(['statut' => 'actif', 'telephone' => '690000002']);
        $groupe->membres()->attach($membre->id);

        $courrier = app(CourrierServiceContract::class)->preparerCourrierSortant([
            'objet' => 'Consigne', 'expediteur' => 'Direction', 'contenu' => 'Consigne interne',
            'cible_type' => 'groupe', 'cible_config' => ['groupe_id' => $groupe->id], 'canal' => 'email',
        ]);

        $this->assertDatabaseHas('courrier_destinataires', [
            'courrier_id' => $courrier->id, 'destinataire_type' => 'utilisateur', 'destinataire_id' => $membre->id,
        ]);

        app(CourrierServiceContract::class)->envoyerCourrier($courrier->id);
        $this->assertNotNull($courrier->fresh()->envoye_le);
        $this->assertDatabaseHas('courrier_destinataires', ['courrier_id' => $courrier->id, 'statut' => 'en_attente']);
    }

    public function test_template_prefills_content_but_remains_variable(): void
    {
        $modele = CourrierModele::create([
            'nom' => 'Convocation', 'objet' => 'Convocation de {nom}',
            'contenu' => 'Bonjour {nom}, rendez-vous le {date}.', 'variables' => ['nom', 'date'], 'actif' => true,
        ]);

        $contenu = app(CourrierServiceContract::class)->appliquerModele($modele->id, ['nom' => 'Paul', 'date' => '10 septembre']);

        $this->assertSame('Convocation de Paul', $contenu['objet']);
        $this->assertSame('Bonjour Paul, rendez-vous le 10 septembre.', $contenu['contenu']);
    }
}
