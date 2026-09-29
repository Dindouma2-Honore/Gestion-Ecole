<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers_sante', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eleve_id')->unique(); // module Scolarité, pas de FK inter-module
            $table->string('groupe_sanguin', 5)->nullable();
            $table->text('allergies')->nullable();
            $table->text('maladies_chroniques')->nullable();
            $table->text('medicaments_autorises')->nullable();
            $table->string('contact_urgence_nom');
            $table->string('contact_urgence_telephone', 20);
            $table->string('medecin_traitant')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers_sante');
    }
};
