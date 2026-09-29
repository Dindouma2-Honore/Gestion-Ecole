<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrets_circuit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circuit_id')->constrained('circuits_transport');
            $table->string('nom');
            $table->tinyInteger('ordre');
            $table->time('heure_passage_matin')->nullable();
            $table->time('heure_passage_soir')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrets_circuit');
    }
};
