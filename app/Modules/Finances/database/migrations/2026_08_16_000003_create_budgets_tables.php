<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('annee_scolaire_id')->unique();
            $table->enum('statut', ['brouillon', 'valide', 'cloture'])->default('brouillon');
            $table->unsignedBigInteger('valide_par')->nullable();
            $table->timestamps();

            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires');
            $table->foreign('valide_par')->references('id')->on('users');
        });

        Schema::create('budget_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->enum('type', ['recette', 'depense']);
            $table->unique(['nom', 'type']);
        });

        Schema::create('budget_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('categorie_id')->constrained('budget_categories');
            $table->decimal('montant_prevu', 12, 2);
            $table->unique(['budget_id', 'categorie_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lignes');
        Schema::dropIfExists('budget_categories');
        Schema::dropIfExists('budgets');
    }
};
