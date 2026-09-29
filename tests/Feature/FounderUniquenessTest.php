<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Socle\Exceptions\FondateurDejaAttribueException;
use App\Modules\Socle\Services\AssignationRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FounderUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Fondateur']);
        Role::firstOrCreate(['name' => 'Comptable']);
    }

    public function test_second_active_founder_is_rejected_by_the_service(): void
    {
        $current = User::factory()->create(['statut' => 'actif']);
        $current->assignRole('Fondateur');
        $candidate = User::factory()->create(['statut' => 'actif']);

        $this->expectException(FondateurDejaAttribueException::class);
        app(AssignationRoleService::class)->assignerRole($candidate, 'Fondateur');
    }

    public function test_founder_transfer_is_atomic_explicit_and_audited(): void
    {
        $current = User::factory()->create(['statut' => 'actif']);
        $current->assignRole('Fondateur');
        $candidate = User::factory()->create(['statut' => 'actif']);
        $candidate->assignRole('Comptable');
        $this->actingAs($current);

        app(AssignationRoleService::class)->transfererFondateur($current, $candidate, 'Départ officiel du fondateur');

        $this->assertFalse($current->fresh()->hasRole('Fondateur'));
        $this->assertTrue($candidate->fresh()->hasRole('Fondateur'));
        $this->assertDatabaseHas('activity_log', [
            'subject_id' => $candidate->id,
            'description' => "Transfert du rôle Fondateur de {$current->email} vers {$candidate->email}",
        ]);
    }

    public function test_founder_transfer_requires_a_reason(): void
    {
        $current = User::factory()->create(['statut' => 'actif']);
        $current->assignRole('Fondateur');

        $this->expectException(InvalidArgumentException::class);
        app(AssignationRoleService::class)->transfererFondateur($current, User::factory()->create(), '');
    }
}
