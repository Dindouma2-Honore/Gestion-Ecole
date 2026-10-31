<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions_pedagogiques', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->string('decision', 40); // admis, redouble, exclu, reoriente, admis_sous_condition
            $table->text('motif')->nullable();
            $table->unsignedBigInteger('valide_par')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();

            $table->unique(['eleve_id', 'annee_scolaire_id']);
            $table->index(['classe_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions_pedagogiques');
    }
};
