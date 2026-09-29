<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('visiteurs')) {
            Schema::create('visiteurs', function (Blueprint $table) {
                $table->id();
                $table->string('nom');
                $table->string('telephone', 20)->nullable();
                $table->string('photo_path')->nullable();
                $table->string('motif');
                $table->nullableMorphs('personne_visitee');
                $table->timestamp('heure_entree');
                $table->timestamp('heure_sortie')->nullable();
                $table->string('badge_numero', 20)->nullable();
                $table->boolean('autorisation_prealable')->default(false);
                $table->boolean('incident_signale')->default(false);
                $table->foreignId('enregistre_par')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visiteurs');
    }
};
