<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupesFraisSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('groupes_frais')->upsert([
            ['code' => 'scolarite', 'nom' => 'Frais de scolarité', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'autres', 'nom' => 'Autres frais', 'created_at' => now(), 'updated_at' => now()],
        ], ['code'], ['nom', 'updated_at']);
    }
}
