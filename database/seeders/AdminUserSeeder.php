<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Administrateur Général',
                'nom' => 'Administrateur',
                'prenom' => 'Général',
                'password' => Hash::make('password'),
                'statut' => 'actif',
            ]
        );

        $role = Role::firstOrCreate(['name' => 'Fondateur']);
        $admin->syncRoles([$role]);
    }
}
