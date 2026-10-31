<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferts_affectations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions');
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->foreignId('classe_origine_id')->constrained('classes');
            $table->foreignId('classe_destination_id')->constrained('classes');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->date('date_transfert');
            $table->text('motif');
            $table->foreignId('effectue_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['eleve_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferts_affectations');
    }
};
