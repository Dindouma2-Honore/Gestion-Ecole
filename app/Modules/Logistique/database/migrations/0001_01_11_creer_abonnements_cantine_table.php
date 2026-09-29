<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements_cantine', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleve_id'); // module Scolarité, pas de FK inter-module
            $table->unsignedBigInteger('annee_scolaire_id'); // module Scolarité
            $table->enum('type', ['mensuel', 'trimestriel', 'annuel']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->decimal('montant', 10, 2);
            $table->enum('statut', ['actif', 'suspendu', 'expire'])->default('actif');

            $table->index('eleve_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements_cantine');
    }
};
