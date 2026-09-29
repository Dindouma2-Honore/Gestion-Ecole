<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances');

            // eleve_id (Scolarité), document_justificatif_id et saisi_par
            // (Socle) : autres modules -> pas de FK inter-module.
            $table->unsignedBigInteger('eleve_id');
            $table->enum('statut', ['present', 'absent', 'retard', 'depart_anticipe']);
            $table->time('heure_arrivee')->nullable(); // pour un retard
            $table->boolean('justifie')->default(false);
            $table->unsignedBigInteger('document_justificatif_id')->nullable();
            $table->unsignedBigInteger('saisi_par')->nullable();
            $table->timestamps();

            $table->unique(['seance_id', 'eleve_id']);
            $table->index('eleve_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
