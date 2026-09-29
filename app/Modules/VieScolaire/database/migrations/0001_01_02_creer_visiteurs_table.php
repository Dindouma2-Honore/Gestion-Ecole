<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visiteurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('telephone')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('motif');

            // personne_visitee : User (employé) ou Eleve (module Scolarité) —
            // relation polymorphique en simples colonnes (pas de morphTo
            // Eloquent inter-module, même convention que le reste du projet).
            $table->string('personne_visitee_type')->nullable();
            $table->unsignedBigInteger('personne_visitee_id')->nullable();

            $table->timestamp('heure_entree');
            $table->timestamp('heure_sortie')->nullable();
            $table->string('badge_numero')->nullable()->unique();
            $table->boolean('autorisation_prealable')->default(false);
            $table->boolean('incident_signale')->default(false);
            $table->unsignedBigInteger('enregistre_par'); // users.id
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visiteurs');
    }
};
