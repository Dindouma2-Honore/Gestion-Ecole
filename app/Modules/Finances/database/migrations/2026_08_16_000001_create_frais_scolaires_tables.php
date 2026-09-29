<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grilles_frais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('niveau_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->enum('type_frais', ['inscription', 'scolarite', 'examen', 'transport', 'cantine']);
            $table->decimal('montant', 10, 2);
            $table->timestamps();

            $table->unique(['niveau_id', 'annee_scolaire_id', 'type_frais']);
            $table->foreign('niveau_id')->references('id')->on('niveaux');
            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires');
        });

        Schema::create('echeanciers_paiement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grille_frais_id');
            $table->string('libelle', 100);
            $table->date('date_echeance');
            $table->decimal('montant', 10, 2);
            $table->unsignedTinyInteger('ordre')->default(1);
            $table->timestamps();

            $table->foreign('grille_frais_id')->references('id')->on('grilles_frais')->onDelete('cascade');
        });

        Schema::create('remises_exonerations', function (Blueprint $table) {
            $table->id();
            // Pas de clé étrangère vers `eleves` : ce module ne connaît le
            // module Scolarité que via EleveServiceInterface, jamais par
            // une table directement (voir règle d'architecture du projet).
            $table->unsignedBigInteger('eleve_id');
            $table->string('type_frais', 50);
            $table->enum('type', ['remise_pourcentage', 'remise_montant', 'exoneration_totale']);
            $table->decimal('valeur', 10, 2);
            $table->string('motif');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->unsignedBigInteger('approuve_par');
            $table->timestamps();

            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires');
            $table->foreign('approuve_par')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remises_exonerations');
        Schema::dropIfExists('echeanciers_paiement');
        Schema::dropIfExists('grilles_frais');
    }
};
