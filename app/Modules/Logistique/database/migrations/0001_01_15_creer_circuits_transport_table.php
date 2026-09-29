<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuits_transport', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100);
            $table->foreignId('vehicule_id')->constrained('vehicules');
            $table->unsignedBigInteger('chauffeur_id'); // module Socle/RH (employé), pas de FK inter-module
            $table->unsignedBigInteger('accompagnateur_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuits_transport');
    }
};
