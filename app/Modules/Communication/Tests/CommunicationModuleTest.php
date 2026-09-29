<?php

declare(strict_types=1);

namespace App\Modules\Communication\Tests;

use App\Models\User;
use App\Modules\Communication\Contracts\AnnonceServiceContract;
use App\Modules\Communication\Contracts\CommunicationParentServiceContract;
use App\Modules\Communication\Contracts\NotificationServiceContract;
use App\Modules\Communication\Contracts\PortailParentServiceContract;
use App\Modules\Communication\Contracts\RendezVousServiceContract;
use App\Modules\Communication\Exceptions\AccesNonAutoriseException;
use App\Modules\Communication\Models\NotificationEnvoyee;
use App\Modules\Communication\Models\RendezVous;
use App\Modules\Finances\Contracts\PaiementServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunicationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(\App\Modules\Communication\Providers\CommunicationServiceProvider::class);
        $this->artisan('migrate', ['--path' => 'app/Modules/Communication/database/migrations']);
    }

    public function test_notification_service_creates_notification_and_queue(): void
    {
        $service = App::make(NotificationServiceContract::class);
        $user = User::factory()->create(['telephone' => '+2250700000000']);

        $notification = $service->envoyer('whatsapp', 'test_code', $user, ['sujet' => 'Test']);

        $this->assertInstanceOf(NotificationEnvoyee::class, $notification);
        $this->assertEquals('en_attente', $notification->statut);
        $this->assertDatabaseHas('notifications_envoyees', [
            'canal' => 'whatsapp',
            'code_template' => 'test_code',
        ]);
        $this->assertDatabaseHas('file_attente_notifications', [
            'notification_id' => $notification->id,
        ]);
    }

    public function test_communication_parent_envoyer_message_individuel(): void
    {
        $service = App::make(CommunicationParentServiceContract::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $parentId = DB::table('parents_tuteurs')->insertGetId([
            'nom' => 'Kouassi',
            'prenom' => 'Jean',
            'telephone' => '+2250102030405',
            'email' => 'jean@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = $service->envoyerMessageIndividuel($parentId, 'Information importante', 'Contenu du message');

        $this->assertEquals('individuel', $message->type);
        $this->assertEquals('Information importante', $message->sujet);
        $this->assertDatabaseHas('messages_parents', [
            'sujet' => 'Information importante',
        ]);
        $this->assertDatabaseHas('message_destinataires', [
            'parent_id' => $parentId,
        ]);
    }

    public function test_rendez_vous_demander_et_confirmer(): void
    {
        $service = App::make(RendezVousServiceContract::class);
        $parentId = DB::table('parents_tuteurs')->insertGetId([
            'nom' => 'Traoré',
            'prenom' => 'Awa',
            'telephone' => '+2250707070707',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $staff = User::factory()->create();

        $rdv = $service->demander($parentId, $staff->id, 'Discussion notes', now()->addDays(2));

        $this->assertInstanceOf(RendezVous::class, $rdv);
        $this->assertEquals('demande', $rdv->statut);

        $service->confirmer($rdv->id, now()->addDays(2));
        $rdv->refresh();

        $this->assertEquals('confirme', $rdv->statut);
    }

    public function test_annonce_publier_et_actives(): void
    {
        $service = App::make(AnnonceServiceContract::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $annonce = $service->publier('Réunion générale', 'Contenu de la réunion', 'generale', null);

        $this->assertDatabaseHas('annonces', [
            'titre' => 'Réunion générale',
            'cible_type' => 'generale',
        ]);

        $actives = $service->getAnnoncesActives('generale');
        $this->assertTrue($actives->pluck('id')->contains($annonce->id));
    }

    public function test_portail_parent_controle_acces(): void
    {
        $service = App::make(PortailParentServiceContract::class);

        $this->expectException(AccesNonAutoriseException::class);
        $service->getVueEnfant(999, 888);
    }

    public function test_portail_parent_expose_historique_paiements_et_recus(): void
    {
        $parentId = DB::table('parents_tuteurs')->insertGetId([
            'nom' => 'Kouassi', 'prenom' => 'Jean', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $eleveId = DB::table('eleves')->insertGetId([
            'nom' => 'Kouassi', 'prenom' => 'Ariane', 'statut' => 'actif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('eleve_parent')->insert([
            'eleve_id' => $eleveId, 'parent_id' => $parentId,
            'responsable_legal' => true, 'responsable_paiement' => true, 'autorise_recuperation' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $paiements = $this->mock(PaiementServiceContract::class);
        $paiements->shouldReceive('getHistoriquePaiements')->once()->with($eleveId)->andReturn(collect([
            ['numero_recu' => 'REC-2026-0001', 'statut' => 'annule', 'url_recu' => 'https://example.test/recu'],
        ]));
        $paiements->shouldReceive('getResteAPayer')->zeroOrMoreTimes()->andReturn(0.0);

        $vue = App::make(PortailParentServiceContract::class)->getVueEnfant($parentId, $eleveId);

        $this->assertSame('REC-2026-0001', $vue->historique_paiements->first()['numero_recu']);
        $this->assertSame('annule', $vue->historique_paiements->first()['statut']);
        $this->assertSame('https://example.test/recu', $vue->historique_paiements->first()['url_recu']);
    }
}
