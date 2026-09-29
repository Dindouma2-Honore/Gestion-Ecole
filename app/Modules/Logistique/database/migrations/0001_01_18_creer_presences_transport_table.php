<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_transport_id')->constrained('inscriptions_transport');
            $table->date('date_trajet');
            $table->enum('trajet', ['matin', 'soir']);
            $table->boolean('present');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences_transport');
    }
};
