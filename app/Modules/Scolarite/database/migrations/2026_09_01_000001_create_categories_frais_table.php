<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catégories de frais — CRUD libre, aucune catégorie n'est imposée par le
 * système (voir Module 5, §1 : "pas de règle de 2 groupes fixes").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_frais', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 100)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories_frais');
    }
};
