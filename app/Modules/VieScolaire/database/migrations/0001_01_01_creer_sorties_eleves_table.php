<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorties_eleves', function (Blueprint $table) {
            $table->id();

            // eleve_id, parent_id : module Scolarité — pas de contrainte FK
            // inter-module (même convention que le reste du projet).
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('personne_autorisee_nom')->nullable();
            $table->enum('type', ['normale', 'exceptionnelle']);
            $table->timestamp('heure_sortie');
            $table->unsignedBigInteger('justificatif_document_id')->nullable(); // module Socle
            $table->unsignedBigInteger('remis_par'); // users.id

            $table->index('eleve_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorties_eleves');
    }
};
