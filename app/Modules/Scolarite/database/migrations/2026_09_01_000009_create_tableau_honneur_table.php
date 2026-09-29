<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composé manuellement par la Direction chaque trimestre — pas de
 * génération automatique par seuil de moyenne (voir Module 5, §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tableau_honneur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves');

            // periode_id (module Pédagogie, pas encore livré dans ce module)
            // et annee_scolaire_id (Socle) : pas de FK inter-module.
            $table->unsignedBigInteger('periode_id');
            $table->unsignedBigInteger('annee_scolaire_id');

            $table->string('mention', 100)->nullable(); // ex: "Excellence", "Tableau d'honneur"
            $table->foreignId('compose_par')->constrained('users');

            // document_id (module Document de Socle, contrat désormais
            // livré et vérifié) : rattachement optionnel d'une pièce
            // justificative — aucune génération automatique dans ce lot,
            // colonne renseignée au besoin via
            // DocumentServiceContract::attacher() ailleurs.
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->timestamps();

            $table->unique(['eleve_id', 'periode_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tableau_honneur');
    }
};
