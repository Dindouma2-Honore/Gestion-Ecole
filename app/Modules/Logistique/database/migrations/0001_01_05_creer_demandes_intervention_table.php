<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_intervention', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipement_id')->nullable()->constrained('equipements');
            $table->foreignId('salle_id')->nullable()->constrained('salles');
            $table->text('description');
            $table->enum('type', ['curative', 'preventive']);
            $table->unsignedBigInteger('technicien_id')->nullable(); // module Socle/RH (employé) ou prestataire externe
            $table->string('technicien_externe_nom')->nullable();
            $table->text('diagnostic')->nullable();
            $table->decimal('cout', 10, 2)->nullable();
            $table->text('pieces_utilisees')->nullable();
            $table->enum('statut', ['signalee', 'diagnostiquee', 'en_reparation', 'terminee'])->default('signalee');
            $table->timestamp('date_signalement')->nullable();
            $table->timestamp('date_reparation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_intervention');
    }
};
