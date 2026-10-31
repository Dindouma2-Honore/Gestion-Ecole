<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('matieres')) {
            Schema::table('matieres', function (Blueprint $table): void {
                if (! Schema::hasColumn('matieres', 'type')) {
                    $table->string('type', 30)->default('matiere')->after('nom')->index();
                }
            });
        }

        if (Schema::hasTable('affectations_pedagogiques')) {
            // Drop previous unique index if exists
            try {
                Schema::table('affectations_pedagogiques', function (Blueprint $table): void {
                    $table->dropUnique('affectation_pedagogique_unique');
                });
            } catch (\Throwable) {
                // Ignore if index didn't exist
            }

            // Drop foreign key if exists to allow nullability
            try {
                Schema::table('affectations_pedagogiques', function (Blueprint $table): void {
                    $table->dropForeign(['matiere_id']);
                });
            } catch (\Throwable) {
                // Ignore if foreign key didn't exist or had different name
            }

            Schema::table('affectations_pedagogiques', function (Blueprint $table): void {
                $table->unsignedBigInteger('matiere_id')->nullable()->change();

                if (! Schema::hasColumn('affectations_pedagogiques', 'type_affectation')) {
                    $table->string('type_affectation', 30)->default('matiere')->after('enseignant_id')->index();
                }

                if (! Schema::hasColumn('affectations_pedagogiques', 'role_precision')) {
                    $table->string('role_precision', 150)->nullable()->after('type_affectation');
                }

                $table->index(['classe_id', 'annee_scolaire_id', 'type_affectation'], 'aff_classe_annee_type_idx');
                $table->index(['enseignant_id', 'annee_scolaire_id'], 'aff_ens_annee_idx');
            });

            // Re-add foreign key on matiere_id with null on delete
            try {
                Schema::table('affectations_pedagogiques', function (Blueprint $table): void {
                    $table->foreign('matiere_id')->references('id')->on('matieres')->nullOnDelete();
                });
            } catch (\Throwable) {
                // Ignore if cannot re-add
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('affectations_pedagogiques')) {
            Schema::table('affectations_pedagogiques', function (Blueprint $table): void {
                if (Schema::hasColumn('affectations_pedagogiques', 'role_precision')) {
                    $table->dropColumn('role_precision');
                }
                if (Schema::hasColumn('affectations_pedagogiques', 'type_affectation')) {
                    $table->dropColumn('type_affectation');
                }
                $table->dropIndex('aff_classe_annee_type_idx');
                $table->dropIndex('aff_ens_annee_idx');
            });
        }

        if (Schema::hasTable('matieres')) {
            Schema::table('matieres', function (Blueprint $table): void {
                if (Schema::hasColumn('matieres', 'type')) {
                    $table->dropColumn('type');
                }
            });
        }
    }
};
