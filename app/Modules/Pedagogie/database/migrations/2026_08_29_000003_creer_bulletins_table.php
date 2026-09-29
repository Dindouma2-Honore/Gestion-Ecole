<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins', function (Blueprint $table): void {
            $table->id();

            // Scolarité / Socle : pas de FK inter-module, uniquement l'identifiant.
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->unsignedBigInteger('periode_id')->nullable();

            $table->decimal('moyenne_generale', 5, 2)->nullable();
            $table->unsignedSmallInteger('rang')->nullable();
            $table->unsignedSmallInteger('effectif_classe')->nullable();

            // Document généré (PDF si disponible, sinon HTML) via
            // Socle\Contracts\DocumentServiceContract.
            $table->unsignedBigInteger('document_id')->nullable();

            $table->unsignedBigInteger('genere_par')->nullable();
            $table->timestamp('genere_le')->nullable();
            $table->string('statut', 20)->default('genere');
            $table->timestamps();

            $table->index(['classe_id', 'annee_scolaire_id', 'periode_id']);
            $table->index(['eleve_id', 'annee_scolaire_id', 'periode_id']);
        });

        Schema::create('bulletin_matieres', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->decimal('moyenne', 5, 2)->nullable();
            $table->decimal('coefficient', 4, 2)->default(1);
            $table->string('appreciation', 100)->nullable();
            $table->timestamps();

            $table->unique(['bulletin_id', 'matiere_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletin_matieres');
        Schema::dropIfExists('bulletins');
    }
};
