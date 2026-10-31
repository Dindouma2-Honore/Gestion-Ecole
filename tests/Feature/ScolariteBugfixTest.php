<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Scolarite\Filament\Widgets\ScolariteOverview;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScolariteBugfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_scolarite_overview_widget_can_render_without_class_not_found_error(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        Livewire::test(ScolariteOverview::class)
            ->assertSuccessful();
    }

    public function test_get_stats_does_not_throw_exception(): void
    {
        $user = User::factory()->create(['statut' => 'actif']);
        $user->assignRole('Fondateur');
        $this->actingAs($user);

        $widget = new ScolariteOverview();
        $reflection = new \ReflectionMethod($widget, 'getStats');
        $reflection->setAccessible(true);
        $stats = $reflection->invoke($widget);

        $this->assertIsArray($stats);
    }
}
