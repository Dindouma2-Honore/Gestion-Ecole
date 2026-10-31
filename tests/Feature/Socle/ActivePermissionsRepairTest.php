<?php

namespace Tests\Feature\Socle;

use App\Modules\Communication\Support\WhatsAppTemplatePermissions;
use Database\Seeders\ActivePermissionsRepairSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivePermissionsRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_permissions_repair_is_idempotent_and_correct(): void
    {
        // 1. Run seeder
        $seeder = new ActivePermissionsRepairSeeder;
        $seeder->run();

        // 2. Verify all required permissions are created with web guard
        $expected = array_merge(
            ['technique.view'],
            ['whatsapp.view', 'whatsapp.connect', 'whatsapp.send', 'whatsapp.simulate'],
            WhatsAppTemplatePermissions::ALL
        );

        $this->assertGreaterThanOrEqual(count($expected), Permission::whereIn('name', $expected)->count());

        foreach ($expected as $permName) {
            $permission = Permission::where('name', $permName)->first();
            $this->assertNotNull($permission);
            $this->assertSame('web', $permission->guard_name);
        }

        // 3. Second execution (idempotency)
        $countBefore = Permission::count();
        $seeder->run();
        $countAfter = Permission::count();

        $this->assertSame($countBefore, $countAfter);

        // 4. Verify existing permissions are not deleted
        $this->assertTrue(Permission::where('name', 'voir_salaires')->exists());

        // 5. Verify no roles or users received these permissions automatically
        foreach (Role::all() as $role) {
            if ($role->name !== 'Fondateur') {
                foreach ($expected as $permName) {
                    $this->assertFalse($role->hasPermissionTo($permName));
                }
            }
        }
    }
}
