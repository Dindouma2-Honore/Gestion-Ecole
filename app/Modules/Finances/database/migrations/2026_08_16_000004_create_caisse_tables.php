<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_caisse', function (Blueprint $table) {
            $table->id();
            $table->date('date_session');
            $table->decimal('solde_ouverture', 10, 2);
            $table->decimal('solde_cloture_theorique', 10, 2)->nullable();
            $table->decimal('solde_cloture_reel', 10, 2)->nullable();
            $table->decimal('ecart', 10, 2)->nullable();
            $table->enum('statut', ['ouverte', 'cloturee'])->default('ouverte');
            $table->unsignedBigInteger('ouverte_par');
            $table->unsignedBigInteger('cloturee_par')->nullable();
            $table->timestamps();

            $table->unique('date_session');
            $table->foreign('ouverte_par')->references('id')->on('users');
            $table->foreign('cloturee_par')->references('id')->on('users');
        });

        Schema::create('mouvements_caisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_caisse_id')->constrained('sessions_caisse');
            $table->enum('type', ['encaissement', 'decaissement']);
            $table->decimal('montant', 10, 2);
            // Relation polymorphique légère vers la classe interne du module
            // producteur (Paiement pour E.40, Depense pour la future E.44) —
            // jamais interrogée directement depuis un autre module.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('justificatif')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_caisse');
        Schema::dropIfExists('sessions_caisse');
    }
};
