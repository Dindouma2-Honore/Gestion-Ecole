<?php

namespace Database\Seeders;

use App\Modules\Communication\Support\WhatsAppTemplatePermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ActivePermissionsRepairSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = array_merge(
            ['technique.view', 'technique.backup.create', 'technique.backup.restore'],
            ['whatsapp.view', 'whatsapp.connect', 'whatsapp.send', 'whatsapp.simulate'],
            WhatsAppTemplatePermissions::ALL
        );

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
