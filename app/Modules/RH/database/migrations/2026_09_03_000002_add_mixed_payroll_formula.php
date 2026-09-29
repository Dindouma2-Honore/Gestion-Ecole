<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE contrats MODIFY categorie_paie ENUM('fixe', 'horaire', 'mixte') NOT NULL DEFAULT 'fixe'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE contrats SET categorie_paie = 'fixe' WHERE categorie_paie = 'mixte'");
            DB::statement("ALTER TABLE contrats MODIFY categorie_paie ENUM('fixe', 'horaire') NOT NULL DEFAULT 'fixe'");
        }
    }
};
