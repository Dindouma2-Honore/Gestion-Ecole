<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipements', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('numero_identification', 50)->unique();
            $table->string('categorie', 100)->nullable();
            $table->foreignId('salle_id')->nullable()->constrained('salles'); // localisation actuelle
            $table->unsignedBigInteger('responsable_id')->nullable(); // module Socle/RH (employé), pas de FK inter-module
            $table->enum('etat', ['bon', 'a_reparer', 'hors_service', 'mis_au_rebut'])->default('bon');
            $table->date('date_acquisition');
            $table->decimal('valeur_acquisition', 10, 2)->nullable();
            $table->date('garantie_fin')->nullable();
            $table->date('date_mise_au_rebut')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipements');
    }
};
