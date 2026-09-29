<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations_livres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livre_id')->constrained('livres');
            $table->string('emprunteur_type');
            $table->unsignedBigInteger('emprunteur_id');
            $table->timestamp('date_reservation')->nullable();
            $table->enum('statut', ['active', 'servie', 'expiree'])->default('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations_livres');
    }
};
