<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'voir_salaires',
            'voir_donnees_medicales',
            'valider_depenses',
            'annuler_paiements',
            'publier_bulletins',
            'gerer_utilisateurs',
            'gerer_parametrage',
            'gerer_annees_scolaires',
            'voir_audit_logs',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $roles = [
            'Fondateur',
            'Directeur',
            'Comptable',
            'SurveillantGeneral',
            'ChargeLogistique',
            'Enseignant',
            'Parent',
            'Eleve',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        // Le Fondateur administre le Socle et reçoit toutes les permissions.
        $fondateur = Role::findByName('Fondateur');
        $fondateur->givePermissionTo(Permission::all());
    }
}
