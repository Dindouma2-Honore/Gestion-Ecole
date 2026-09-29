<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exemplaires_livres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('livre_id')->constrained('livres');
            $table->string('code_exemplaire', 30)->unique();
            $table->enum('etat', ['bon', 'abime', 'perdu'])->default('bon');
            $table->boolean('disponible')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exemplaires_livres');
    }
};
