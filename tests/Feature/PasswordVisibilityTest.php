<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_passwords_are_revealable_across_the_admin_panel(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->arePasswordsRevealable());
    }

    public function test_every_custom_password_field_is_revealable(): void
    {
        foreach ([
            app_path('Filament/Pages/ChangerMotDePasse.php'),
            app_path('Modules/Socle/Filament/Resources/UserResource.php'),
        ] as $file) {
            $source = file_get_contents($file);

            $this->assertIsString($source);
            $this->assertSame(
                substr_count($source, '->password()'),
                substr_count($source, '->revealable()'),
                "Chaque champ de mot de passe de {$file} doit pouvoir être affiché.",
            );
        }
    }

    public function test_user_can_open_the_mandatory_password_change_page(): void
    {
        $user = User::factory()->create([
            'statut' => 'actif',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin/changer-mot-de-passe')
            ->assertSuccessful()
            ->assertSee('Modification obligatoire du mot de passe');
    }
}
