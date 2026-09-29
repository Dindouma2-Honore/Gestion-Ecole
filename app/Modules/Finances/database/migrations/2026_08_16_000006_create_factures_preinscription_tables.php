<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factures_preinscription', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('inscription_id')->unique();
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->string('eleve_nom');
            $table->string('parent_nom');
            $table->string('parent_email');
            $table->decimal('montant_total', 12, 2);
            $table->enum('statut', ['en_attente_versement', 'payee'])->default('en_attente_versement');
            $table->string('mode_paiement')->nullable();
            $table->string('reference_transaction')->nullable();
            $table->unsignedBigInteger('paiement_id')->nullable();
            $table->unsignedBigInteger('document_provisoire_id')->nullable();
            $table->unsignedBigInteger('validee_par')->nullable();
            $table->timestamp('envoyee_le')->nullable();
            $table->timestamp('payee_le')->nullable();
            $table->timestamps();

            $table->foreign('validee_par')->references('id')->on('users');
        });

        Schema::create('facture_preinscription_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_preinscription_id')->constrained('factures_preinscription')->cascadeOnDelete();
            $table->string('type_frais', 50);
            $table->string('libelle');
            $table->decimal('montant', 12, 2);
            $table->boolean('obligatoire')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_preinscription_lignes');
        Schema::dropIfExists('factures_preinscription');
    }
};
