<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\EditProfile;
use App\Models\User;
use App\Modules\Socle\Filament\Resources\UserResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_authenticated_user_can_update_display_name_and_phone(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Nom affiché librement',
                'telephone' => '+237 699 000 000',
                'email' => $user->email,
                'locale' => 'fr',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nom affiché librement',
            'telephone' => '+237 699 000 000',
        ]);
    }

    public function test_user_creation_is_disabled_in_administration(): void
    {
        $this->assertFalse(UserResource::shouldRegisterNavigation());
        $this->assertFalse(UserResource::canCreate());
    }
}
