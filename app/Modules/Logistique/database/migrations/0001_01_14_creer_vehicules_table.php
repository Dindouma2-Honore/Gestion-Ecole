<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicules', function (Blueprint $table) {
            $table->id();
            $table->string('immatriculation', 20)->unique();
            $table->unsignedInteger('capacite');
            $table->enum('etat', ['bon', 'en_maintenance', 'hors_service'])->default('bon');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicules');
    }
};
