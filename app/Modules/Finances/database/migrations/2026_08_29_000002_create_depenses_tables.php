<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubriques_depenses', function (Blueprint $table): void {
            $table->id();
            $table->string('nom', 120)->unique();
            $table->boolean('active')->default(true);
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('depenses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->foreignId('rubrique_depense_id')->constrained('rubriques_depenses')->restrictOnDelete();
            $table->string('libelle', 180);
            $table->decimal('montant', 12, 2);
            $table->date('date_depense');
            $table->enum('statut', ['brouillon', 'en_attente_validation', 'validee', 'payee', 'rejetee'])->default('brouillon');
            $table->text('motif')->nullable();
            $table->string('justificatif')->nullable();
            $table->foreignId('cree_par')->constrained('users')->restrictOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_le')->nullable();
            $table->timestamp('payee_le')->nullable();
            $table->timestamps();
            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires')->restrictOnDelete();
            $table->index(['annee_scolaire_id', 'rubrique_depense_id', 'statut'], 'depenses_annee_rubrique_statut_index');
            $table->index(['date_depense', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depenses');
        Schema::dropIfExists('rubriques_depenses');
    }
};
