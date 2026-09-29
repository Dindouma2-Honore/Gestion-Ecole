<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->string('nom'); // "1er Trimestre", "Séquence 1"
            $table->enum('type', ['trimestre', 'semestre', 'sequence'])->default('trimestre');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedTinyInteger('ordre')->default(1);
            $table->boolean('cloturee')->default(false);
            $table->timestamps();

            $table->foreign('annee_scolaire_id')->references('id')->on('annees_scolaires')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes');
    }
};
