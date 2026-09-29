<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();

            // niveau_id et annee_scolaire_id appartiennent respectivement aux modules
            // Scolarité et Socle : jamais de contrainte de clé étrangère inter-module,
            // uniquement l'identifiant, validé via leur Contract au niveau du Service.
            $table->unsignedBigInteger('niveau_id');
            $table->unsignedBigInteger('annee_scolaire_id');

            $table->string('titre', 200);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->enum('statut', ['brouillon', 'soumis', 'valide', 'rejete', 'publie'])->default('valide');
            $table->timestamps();

            $table->index(['niveau_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
