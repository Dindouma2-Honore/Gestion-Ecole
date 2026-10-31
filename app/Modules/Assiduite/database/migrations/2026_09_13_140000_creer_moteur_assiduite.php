<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assiduite_journaliere', function (Blueprint $table): void {
            $table->id();
            $table->enum('personne_type', ['eleve', 'personnel']);
            $table->unsignedBigInteger('personne_id');
            $table->date('date_assiduite');
            $table->string('statut', 30);
            $table->time('heure_attendue')->nullable();
            $table->time('heure_arrivee')->nullable();
            $table->time('heure_depart')->nullable();
            $table->unsignedInteger('minutes_retard')->default(0);
            $table->unsignedInteger('minutes_depart_anticipe')->default(0);
            $table->unsignedInteger('minutes_presence')->default(0);
            $table->string('source', 40);
            $table->unsignedTinyInteger('confiance')->default(100);
            $table->json('details')->nullable();
            $table->timestamp('calcule_le');
            $table->timestamps();
            $table->unique(['personne_type', 'personne_id', 'date_assiduite'], 'assiduite_personne_jour_unique');
            $table->index(['date_assiduite', 'statut']);
        });

        Schema::create('pointages_bruts', function (Blueprint $table): void {
            $table->id();
            $table->string('cle_idempotence', 64)->unique();
            $table->string('terminal_id', 100);
            $table->string('identifiant_biometrique', 100);
            $table->unsignedBigInteger('employe_id')->nullable();
            $table->dateTime('horodatage');
            $table->string('type_pointage', 20)->nullable();
            $table->string('source', 30)->default('biometrie');
            $table->json('payload')->nullable();
            $table->timestamp('importe_le');
            $table->string('statut_import', 30)->default('importe');
            $table->text('erreur')->nullable();
            $table->timestamps();
            $table->index(['employe_id', 'horodatage']);
        });

        Schema::create('justifications_assiduite', function (Blueprint $table): void {
            $table->id();
            $table->enum('personne_type', ['eleve', 'personnel']);
            $table->unsignedBigInteger('personne_id');
            $table->string('type', 30);
            $table->date('date_concernee');
            $table->text('motif');
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('soumis_par');
            $table->timestamp('soumis_le');
            $table->enum('decision', ['en_attente', 'acceptee', 'refusee'])->default('en_attente');
            $table->unsignedBigInteger('decide_par')->nullable();
            $table->timestamp('decide_le')->nullable();
            $table->text('commentaire_decision')->nullable();
            $table->timestamps();
            $table->index(['personne_type', 'personne_id', 'date_concernee'], 'justification_personne_jour_index');
        });

        Schema::create('anomalies_assiduite', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 60);
            $table->string('personne_type', 20)->nullable();
            $table->unsignedBigInteger('personne_id')->nullable();
            $table->date('date_anomalie');
            $table->string('source', 40);
            $table->text('description');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->enum('statut', ['detectee', 'a_verifier', 'corrigee', 'cloturee', 'ignoree'])->default('detectee');
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('resolue_par')->nullable();
            $table->timestamp('resolue_le')->nullable();
            $table->timestamps();
            $table->index(['date_anomalie', 'statut']);
        });

        Schema::create('assiduite_historiques', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assiduite_journaliere_id')->constrained('assiduite_journaliere')->cascadeOnDelete();
            $table->string('action', 30);
            $table->string('ancien_statut', 30)->nullable();
            $table->string('nouveau_statut', 30)->nullable();
            $table->unsignedBigInteger('effectue_par')->nullable();
            $table->text('motif')->nullable();
            $table->timestamp('effectue_le');
        });

        Schema::create('migrations_assiduite_legacy', function (Blueprint $table): void {
            $table->id();
            $table->string('lot', 50);
            $table->string('table_source');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('resultat', 30);
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->text('details')->nullable();
            $table->timestamp('traite_le');
            $table->unique(['lot', 'table_source', 'source_id'], 'migration_assiduite_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migrations_assiduite_legacy');
        Schema::dropIfExists('assiduite_historiques');
        Schema::dropIfExists('anomalies_assiduite');
        Schema::dropIfExists('justifications_assiduite');
        Schema::dropIfExists('pointages_bruts');
        Schema::dropIfExists('assiduite_journaliere');
    }
};
