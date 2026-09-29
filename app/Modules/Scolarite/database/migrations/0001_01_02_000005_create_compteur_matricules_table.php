<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compteur_matricules', function (Blueprint $table) {
            $table->id();

            // annee_scolaire_id : module Socle -> pas de contrainte FK inter-module,
            // même convention que le reste du module Scolarité.
            $table->unsignedBigInteger('annee_scolaire_id')->unique();
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteur_matricules');
    }
};
