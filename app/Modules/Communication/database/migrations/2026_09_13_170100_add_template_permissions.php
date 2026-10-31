<?php

use App\Modules\Communication\Support\WhatsAppTemplatePermissions;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (WhatsAppTemplatePermissions::ALL as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        foreach (Role::where('guard_name', 'web')->whereIn('name', ['Fondateur', 'Super Admin'])->get() as $role) {
            $role->givePermissionTo(array_diff(WhatsAppTemplatePermissions::ALL, ['delete_whatsapp_template_provider']));
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void {}
};
