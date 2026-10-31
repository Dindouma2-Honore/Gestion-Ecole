<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offres_pedagogiques', function (Blueprint $table): void {
            // L'unicité est désormais garantie par matière + classe + année.
            $table->dropUnique('offre_pedagogique_unique');
        });
    }

    public function down(): void
    {
        Schema::table('offres_pedagogiques', function (Blueprint $table): void {
            $table->unique(['matiere_id', 'niveau_id', 'filiere_id', 'annee_scolaire_id'], 'offre_pedagogique_unique');
        });
    }
};
