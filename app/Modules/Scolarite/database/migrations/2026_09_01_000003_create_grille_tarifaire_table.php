<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grille tarifaire par classe — s'applique uniquement aux frais de la
 * catégorie "Frais de scolarité" (inscription, tranche 1, tranche 2). Les
 * autres frais ont un montant unique, non variable par classe (voir
 * Frais::montant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grille_tarifaire', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frais_id')->constrained('frais');
            $table->foreignId('classe_id')->constrained('classes');

            // annee_scolaire_id (Socle) : pas de FK inter-module, même
            // convention que le reste du module Scolarité.
            $table->unsignedBigInteger('annee_scolaire_id');

            $table->decimal('montant', 10, 2);
            $table->timestamps();

            $table->unique(['frais_id', 'classe_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grille_tarifaire');
    }
};
