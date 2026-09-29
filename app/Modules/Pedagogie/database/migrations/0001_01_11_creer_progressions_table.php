<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progressions', function (Blueprint $table) {
            $table->id();

            // seance_id (Assiduité) et saisi_par (Socle) : autres modules ->
            // pas de contrainte de clé étrangère inter-module.
            $table->unsignedBigInteger('seance_id')->unique();

            // chapitre_id référencera programme_chapitres, sous-module de
            // Pédagogie pas encore livré (voir README) -> nullable, pas de FK
            // tant que la table n'existe pas.
            $table->unsignedBigInteger('chapitre_id')->nullable();

            $table->text('contenu_couvert');
            $table->text('devoirs_donnes')->nullable();
            $table->unsignedBigInteger('saisi_par')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progressions');
    }
};
