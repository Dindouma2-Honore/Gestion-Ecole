<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evenement_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements');

            // participant : Eleve ou Employe — relation polymorphique en
            // simples colonnes (pas de morphTo Eloquent inter-module,
            // même convention que le reste du projet).
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');

            $table->boolean('autorisation_parentale_recue')->default(false);
            $table->unsignedBigInteger('document_autorisation_id')->nullable(); // module Socle
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenement_participants');
    }
};
