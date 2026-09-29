<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions_transport', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleve_id'); // module Scolarité, pas de FK inter-module
            $table->foreignId('circuit_id')->constrained('circuits_transport');
            $table->foreignId('arret_id')->constrained('arrets_circuit');
            $table->unsignedBigInteger('annee_scolaire_id'); // module Scolarité
            $table->enum('statut', ['actif', 'suspendu'])->default('actif');

            $table->index('eleve_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions_transport');
    }
};
