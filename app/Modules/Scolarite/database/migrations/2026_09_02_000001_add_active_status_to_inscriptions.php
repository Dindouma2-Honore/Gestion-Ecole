<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE inscriptions MODIFY statut ENUM('en_cours', 'en_attente_versement', 'active', 'validee', 'annulee') NOT NULL DEFAULT 'en_cours'");
    }

    public function down(): void
    {
        DB::table('inscriptions')->where('statut', 'active')->update(['statut' => 'en_attente_versement']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inscriptions MODIFY statut ENUM('en_cours', 'en_attente_versement', 'validee', 'annulee') NOT NULL DEFAULT 'en_cours'");
        }
    }
};
