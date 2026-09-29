<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relances_paiement', function (Blueprint $table) {
            $table->id();
            // Pas de clé étrangère vers `eleves` : ce module ne connaît le
            // module Scolarité que via EleveServiceInterface, jamais par
            // une table directement (voir règle d'architecture du projet).
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedTinyInteger('niveau_relance')->default(1);
            $table->enum('canal', ['sms', 'whatsapp', 'lettre']);
            $table->timestamp('date_relance');
            $table->decimal('reste_a_payer_constate', 10, 2);
            $table->timestamps();

            $table->index(['eleve_id', 'date_relance']);
        });

        Schema::create('echeanciers_negocies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleve_id');
            $table->decimal('montant_total', 10, 2);
            $table->unsignedTinyInteger('nombre_tranches');
            $table->date('date_premiere_tranche');
            $table->enum('statut', ['propose', 'accepte', 'respecte', 'rompu'])->default('propose');
            $table->unsignedBigInteger('approuve_par');
            $table->timestamps();

            $table->foreign('approuve_par')->references('id')->on('users');
        });

        Schema::create('promesses_paiement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleve_id');
            $table->date('date_promesse');
            $table->decimal('montant_promis', 10, 2);
            $table->boolean('tenue')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promesses_paiement');
        Schema::dropIfExists('echeanciers_negocies');
        Schema::dropIfExists('relances_paiement');
    }
};
