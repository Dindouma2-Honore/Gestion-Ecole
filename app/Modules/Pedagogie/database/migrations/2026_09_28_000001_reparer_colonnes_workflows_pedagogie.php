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
        // Some imported databases recorded the workflow migration without all its columns.
        $definitions = [
            'emplois_du_temps' => [
                'statut' => fn (Blueprint $table) => $table->string('statut', 20)->default('brouillon')->index(),
                'publie_par' => fn (Blueprint $table) => $table->unsignedBigInteger('publie_par')->nullable(),
                'publie_le' => fn (Blueprint $table) => $table->timestamp('publie_le')->nullable(),
            ],
            'programme_chapitres' => [
                'objectif' => fn (Blueprint $table) => $table->text('objectif')->nullable(),
                'contenu' => fn (Blueprint $table) => $table->longText('contenu')->nullable(),
                'volume_horaire_prevu' => fn (Blueprint $table) => $table->decimal('volume_horaire_prevu', 6, 2)->nullable(),
                'ressources' => fn (Blueprint $table) => $table->text('ressources')->nullable(),
                'statut' => fn (Blueprint $table) => $table->string('statut', 20)->default('a_faire'),
            ],
            'notes' => [
                'statut' => fn (Blueprint $table) => $table->string('statut', 20)->default('brouillon')->index(),
                'soumis_par' => fn (Blueprint $table) => $table->unsignedBigInteger('soumis_par')->nullable(),
                'soumis_le' => fn (Blueprint $table) => $table->timestamp('soumis_le')->nullable(),
                'valide_par' => fn (Blueprint $table) => $table->unsignedBigInteger('valide_par')->nullable(),
                'valide_le' => fn (Blueprint $table) => $table->timestamp('valide_le')->nullable(),
            ],
            'presences' => [
                'statut_justification' => fn (Blueprint $table) => $table->string('statut_justification', 20)->nullable()->index(),
                'justification_verifiee_par' => fn (Blueprint $table) => $table->unsignedBigInteger('justification_verifiee_par')->nullable(),
                'justification_verifiee_le' => fn (Blueprint $table) => $table->timestamp('justification_verifiee_le')->nullable(),
            ],
        ];

        foreach ($definitions as $name => $columns) {
            $existing = Schema::getColumnListing($name);
            foreach ($columns as $column => $definition) {
                if (! in_array($column, $existing, true)) {
                    Schema::table($name, $definition);
                }
            }

            // Backfill only newly added statuses; never reset an existing workflow.
            if (! in_array('statut', $existing, true)) {
                if ($name === 'notes') {
                    DB::table($name)->where('verrouillee', true)->update(['statut' => 'verrouille']);
                } elseif ($name === 'emplois_du_temps') {
                    DB::table($name)->where('actif', true)->update(['statut' => 'publie']);
                }
            }
        }
    }

    public function down(): void
    {
        // Shared workflow columns may predate this repair and contain live data.
        // Keep them when rolling back this additive repair.
    }
};
