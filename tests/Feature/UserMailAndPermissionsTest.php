<?php

namespace Tests\Feature;

use App\Mail\BienvenueUserMail;
use App\Mail\ReunionOrganiseeMail;
use App\Mail\TacheAssigneeMail;
use App\Mail\TacheValideeMail;
use App\Models\User;
use App\Modules\Socle\Contracts\ReunionServiceContract;
use App\Modules\Socle\Contracts\TacheServiceContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserMailAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Fondateur', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    public function test_user_creation_dispatches_welcome_mail()
    {
        Mail::fake();

        $user = User::create([
            'name' => 'Jean Dupont',
            'email' => 'j.dupont@example.com',
            'password' => 'password123',
            'statut' => 'actif',
            'must_change_password' => true,
        ]);

        Mail::assertSent(BienvenueUserMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_meeting_creation_dispatches_participant_mails()
    {
        Mail::fake();

        $createur = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'statut' => 'actif',
            'must_change_password' => false,
        ]);
        $createur->assignRole('Fondateur');

        $participant = User::create([
            'name' => 'Participant One',
            'email' => 'participant@example.com',
            'password' => 'password123',
            'statut' => 'actif',
            'must_change_password' => false,
        ]);

        $this->actingAs($createur);

        $reunionService = app(ReunionServiceContract::class);
        $reunion = $reunionService->planifierReunion(
            [
                'titre' => 'Réunion Conseil de Classe',
                'type' => 'pedagogique',
                'date_heure' => now()->addDays(2),
                'lieu' => 'Salle des profs',
            ],
            [$participant->id],
            ['Ordre 1: Bilan', 'Ordre 2: Divers']
        );

        Mail::assertSent(ReunionOrganiseeMail::class, function ($mail) use ($participant) {
            return $mail->hasTo($participant->email);
        });
    }

    public function test_task_creation_and_validation_dispatches_mails()
    {
        Mail::fake();

        $createur = User::create([
            'name' => 'Directeur',
            'email' => 'directeur@example.com',
            'password' => 'password123',
            'statut' => 'actif',
            'must_change_password' => false,
        ]);

        $responsable = User::create([
            'name' => 'Responsable Tache',
            'email' => 'responsable@example.com',
            'password' => 'password123',
            'statut' => 'actif',
            'must_change_password' => false,
        ]);

        $this->actingAs($createur);

        $tacheService = app(TacheServiceContract::class);
        $tache = $tacheService->creerTache(
            titre: 'Préparer le rapport financier',
            responsableId: $responsable->id,
            echeance: now()->addWeek(),
            priorite: 'haute',
            description: 'Rapport annuel'
        );

        Mail::assertSent(TacheAssigneeMail::class, function ($mail) use ($responsable) {
            return $mail->hasTo($responsable->email);
        });

        // Demarrer et valider le circuit
        $tacheService->demarrerCircuitValidation($tache->id, [$createur->id]);
        $tacheService->validerEtape($tache->id, $createur->id, true, 'Validé sans réserve');

        Mail::assertSent(TacheValideeMail::class, function ($mail) use ($responsable) {
            return $mail->hasTo($responsable->email);
        });
    }

    public function test_force_password_change_middleware_redirects()
    {
        $user = User::create([
            'name' => 'Nouveau Membre',
            'email' => 'nouveau@example.com',
            'password' => 'password123',
            'statut' => 'actif',
            'must_change_password' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertRedirect('/admin/changer-mot-de-passe');
    }
}
