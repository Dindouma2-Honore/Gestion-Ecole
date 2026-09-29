<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emploi_du_temps_id')->constrained('emplois_du_temps');
            $table->date('date_seance');

            // Peut différer de l'enseignant théorique de l'emploi du temps
            // (remplacement) : appartient au module RH, pas de FK inter-module.
            $table->unsignedBigInteger('enseignant_id');

            $table->enum('statut', [
                'programmee', 'commencee', 'dispensee', 'annulee', 'reportee', 'non_dispensee',
            ])->default('programmee');
            $table->boolean('progression_renseignee')->default(false); // mis à jour par le module Pédagogie
            $table->timestamps();

            $table->unique(['emploi_du_temps_id', 'date_seance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
