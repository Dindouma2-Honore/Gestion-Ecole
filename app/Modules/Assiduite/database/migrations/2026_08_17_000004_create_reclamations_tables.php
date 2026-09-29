<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reclamations')) {
            Schema::create('reclamations', function (Blueprint $table) {
                $table->id();
                $table->enum('type', ['reclamation_parent', 'incident_scolaire', 'plainte']);
                $table->string('categorie', 100)->nullable();
                $table->text('description');
                $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
                $table->nullableMorphs('signale_par');
                $table->foreignId('responsable_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('delai_reponse')->nullable();
                $table->string('statut', 30)->default('ouverte');
                $table->text('reponse')->nullable();
                $table->unsignedBigInteger('tache_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('reclamation_historique_statuts')) {
            Schema::create('reclamation_historique_statuts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reclamation_id')->constrained('reclamations')->cascadeOnDelete();
                $table->string('ancien_statut', 30)->nullable();
                $table->string('nouveau_statut', 30);
                $table->foreignId('modifie_par')->nullable()->constrained('users')->nullOnDelete();
                $table->text('commentaire')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reclamation_historique_statuts');
        Schema::dropIfExists('reclamations');
    }
};
