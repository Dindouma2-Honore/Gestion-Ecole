<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('catalogue_fonctionnalites')->insertOrIgnore([
            'code' => 'module.technique', 'nom' => 'Technique', 'description' => 'Centre d’exploitation technique',
            'categorie' => 'Technique', 'module_technique' => 'Technique', 'ordre' => 100,
            'actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Permission::findOrCreate('technique.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'technique.view')->where('guard_name', 'web')->delete();
        DB::table('catalogue_fonctionnalites')->where('code', 'module.technique')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
