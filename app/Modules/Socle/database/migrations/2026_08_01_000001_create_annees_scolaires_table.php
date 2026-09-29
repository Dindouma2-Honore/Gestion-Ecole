<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annees_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');              // "2026-2027"
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['brouillon', 'active', 'cloturee', 'archivee'])
                ->default('brouillon');
            $table->timestamps();

            $table->unique('libelle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annees_scolaires');
    }
};
