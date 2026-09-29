<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compteurs_matricules', function (Blueprint $table) {
            $table->id();

            // annee_scolaire_id (Socle) : pas de FK inter-module.
            $table->unsignedBigInteger('annee_scolaire_id')->unique();
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eleve_id')->constrained('eleves');
            $table->foreignId('classe_id')->constrained('classes');

            // annee_scolaire_id (Socle) : pas de FK inter-module.
            $table->unsignedBigInteger('annee_scolaire_id');

            $table->enum('type', ['inscription', 'reinscription', 'transfert'])->default('inscription');
            $table->date('date_inscription');
            // 'active' ajouté (voir 2026_09_02_000001_add_active_status_to_inscriptions.php
            // pour la même modification côté MySQL) : InscriptionService::activerApresVersement()
            // et EleveService::getEleve()/getElevesParClasse() l'utilisent comme statut réel
            // une fois le versement confirmé — ce n'était pas dans l'enum d'origine.
            $table->enum('statut', ['en_cours', 'en_attente_versement', 'active', 'validee', 'annulee'])->default('en_cours');
            $table->softDeletes();
            $table->timestamps();

            // Un élève ne peut avoir qu'une seule inscription active pour la
            // même année scolaire (RG-07 du document de conception Module 2).
            $table->unique(['eleve_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
        Schema::dropIfExists('compteurs_matricules');
    }
};
