<?php

namespace Tests\Feature;

use App\Mail\BienvenueUserMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserWelcomeMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_email_is_sent_to_the_created_user_email_address(): void
    {
        Mail::fake();

        // Simulate Admin creating a new user
        $newUser = User::create([
            'name' => 'Jean Dupont',
            'nom' => 'Dupont',
            'prenom' => 'Jean',
            'email' => 'jean.dupont.test@example.com',
            'password' => 'SecretPass123!',
            'statut' => 'actif',
        ]);

        // Assert that the email was sent EXACTLY to the new user's email address
        Mail::assertSent(BienvenueUserMail::class, function (BienvenueUserMail $mail) use ($newUser) {
            return $mail->hasTo($newUser->email) && ! $mail->hasTo('deffoderrick993@gmail.com');
        });
    }
}
