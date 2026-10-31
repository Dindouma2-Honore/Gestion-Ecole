<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('matieres', 'nature')) {
            Schema::table('matieres', fn (Blueprint $t) => $t->string('nature', 20)->default('matiere'));
        }
        Schema::table('affectations_pedagogiques', function (Blueprint $t): void {
            $t->unsignedBigInteger('matiere_id')->nullable()->change();
            if (! Schema::hasColumn('affectations_pedagogiques', 'type')) {
                $t->string('type', 20)->default('matiere')->index();
            }
            if (! Schema::hasColumn('affectations_pedagogiques', 'date_debut')) {
                $t->date('date_debut')->nullable();
            }
            if (! Schema::hasColumn('affectations_pedagogiques', 'date_fin')) {
                $t->date('date_fin')->nullable();
            }
            if (! Schema::hasIndex('affectations_pedagogiques', 'affectation_contexte_index')) {
                $t->index(['annee_scolaire_id', 'classe_id', 'actif'], 'affectation_contexte_index');
            }
            if (Schema::hasIndex('affectations_pedagogiques', 'affectation_pedagogique_unique')) {
                $t->dropUnique('affectation_pedagogique_unique');
            }
        });
        if (! Schema::hasTable('affectation_pedagogique_matieres')) {
            Schema::create('affectation_pedagogique_matieres', function (Blueprint $t): void {
                $t->unsignedBigInteger('affectation_pedagogique_id');
                $t->unsignedBigInteger('matiere_id');
            });
        }
        $foreignKeys = collect(Schema::getForeignKeys('affectation_pedagogique_matieres'))->pluck('name');
        Schema::table('affectation_pedagogique_matieres', function (Blueprint $t) use ($foreignKeys): void {
            if (! $foreignKeys->contains('affectation_perimetre_affectation_fk')) {
                $t->foreign('affectation_pedagogique_id', 'affectation_perimetre_affectation_fk')->references('id')->on('affectations_pedagogiques')->restrictOnDelete();
            }
            if (! $foreignKeys->contains('affectation_perimetre_matiere_fk')) {
                $t->foreign('matiere_id', 'affectation_perimetre_matiere_fk')->references('id')->on('matieres')->restrictOnDelete();
            }
            if (! Schema::hasIndex('affectation_pedagogique_matieres', ['affectation_pedagogique_id', 'matiere_id'], 'primary')) {
                $t->primary(['affectation_pedagogique_id', 'matiere_id'], 'affectation_perimetre_unique');
            }
        });
        if (! Schema::hasTable('organisation_pedagogique_verrous')) {
            Schema::create('organisation_pedagogique_verrous', function (Blueprint $t): void {
                $t->unsignedBigInteger('annee_scolaire_id')->primary();
            });
        }
        if (! Schema::hasTable('organisation_pedagogique_cycles')) {
            Schema::create('organisation_pedagogique_cycles', function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('cycle_id')->unique();
                $t->string('mode', 20)->nullable();
                $t->timestamps();
            });
        }
        // Initialize settings for existing cycles only. New cycles require explicit configuration.
        foreach (DB::table('cycles')->get(['id', 'code']) as $cycle) {
            DB::table('organisation_pedagogique_cycles')->insertOrIgnore(['cycle_id' => $cycle->id,
                'mode' => match ($cycle->code) {
                    'MAT','PRI' => 'classe','COL','LYC' => 'matiere',default => null
                },
                'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    { /* Forward-only: assignments and their history must be retained. */
    }
};
