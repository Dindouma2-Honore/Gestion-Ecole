<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'connect', 'send', 'simulate'] as $action) {
            Permission::findOrCreate('whatsapp.'.$action, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', ['whatsapp.view', 'whatsapp.connect', 'whatsapp.send', 'whatsapp.simulate'])->delete();
    }
};
