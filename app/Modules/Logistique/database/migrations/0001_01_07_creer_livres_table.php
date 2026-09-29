<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('livres', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('auteur')->nullable();
            $table->string('isbn', 20)->nullable();
            $table->string('categorie', 100)->nullable();
            $table->unsignedBigInteger('niveau_id')->nullable(); // module Scolarité, niveau recommandé — nullable si tout public
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('livres');
    }
};
