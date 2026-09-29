<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 50); // 'CP1 A', 'Terminale C'...

            // niveau_id (référentiel Socle) et annee_scolaire_id (Socle) :
            // pas de contrainte de clé étrangère inter-module.
            $table->unsignedBigInteger('niveau_id');
            $table->unsignedBigInteger('annee_scolaire_id');

            $table->unsignedInteger('capacite_max');

            // professeur_principal_id : module RH, pas encore livré -> pas de FK.
            $table->unsignedBigInteger('professeur_principal_id')->nullable();

            $table->timestamps();

            $table->unique(['nom', 'annee_scolaire_id']);
            $table->index(['niveau_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
