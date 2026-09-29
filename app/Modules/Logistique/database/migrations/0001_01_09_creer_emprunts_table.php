<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emprunts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exemplaire_id')->constrained('exemplaires_livres');

            // emprunteur : Eleve ou Employe — relation polymorphique en
            // simples colonnes (pas de morphTo Eloquent inter-module,
            // même convention que le reste du projet).
            $table->string('emprunteur_type');
            $table->unsignedBigInteger('emprunteur_id');

            $table->date('date_emprunt');
            $table->date('date_retour_prevue');
            $table->date('date_retour_reelle')->nullable();
            $table->decimal('penalite', 6, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emprunts');
    }
};
