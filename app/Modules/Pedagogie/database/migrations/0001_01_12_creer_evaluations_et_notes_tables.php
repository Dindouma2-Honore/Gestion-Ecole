<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->string('titre');
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->unsignedBigInteger('classe_id'); // Scolarité : pas de FK inter-module.
            $table->date('date_evaluation');
            $table->decimal('bareme', 8, 2)->default(20);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['classe_id', 'date_evaluation']);
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->unsignedBigInteger('eleve_id'); // Scolarité : pas de FK inter-module.
            $table->decimal('valeur', 8, 2);
            $table->timestamp('premiere_saisie_at');
            $table->timestamp('debloque_le')->nullable();
            $table->timestamp('deblocage_consomme_le')->nullable();
            $table->unsignedBigInteger('deblocage_autorise_par')->nullable();
            $table->text('motif_deblocage')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_id', 'eleve_id']);
            $table->index('eleve_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('evaluations');
    }
};
