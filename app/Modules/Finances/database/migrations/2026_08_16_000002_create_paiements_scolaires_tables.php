<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            // Référence logique vers Scolarité, sans relation Eloquent ni FK
            // inter-module : toute lecture d'élève passe par son Contract.
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->decimal('montant', 10, 2);
            $table->enum('mode', ['especes', 'bancaire', 'mobile_money']);
            $table->string('reference_mobile_money', 100)->nullable();
            $table->string('numero_recu', 50)->unique();
            $table->enum('statut', ['valide', 'annule', 'rembourse'])->default('valide');
            $table->unsignedBigInteger('encaisse_par');
            $table->unsignedBigInteger('document_recu_id')->nullable();
            $table->timestamps();

            $table->index(['eleve_id', 'annee_scolaire_id', 'statut']);
            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires');
            $table->foreign('encaisse_par')->references('id')->on('users');
        });

        Schema::create('paiement_annulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paiement_id')->constrained('paiements');
            $table->text('motif');
            $table->unsignedBigInteger('annule_par');
            $table->timestamp('annule_le');

            $table->foreign('annule_par')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_annulations');
        Schema::dropIfExists('paiements');
    }
};
