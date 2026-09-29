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
        Schema::table('evaluations', function (Blueprint $table): void {
            if (! Schema::hasColumn('evaluations', 'statut')) {
                $table->string('statut', 20)->default('valide')->after('created_by');
            }

            if (! Schema::hasColumn('evaluations', 'document_sujet_id')) {
                $table->unsignedBigInteger('document_sujet_id')->nullable()->after('statut');
            }

            if (! Schema::hasColumn('evaluations', 'valide_par')) {
                $table->unsignedBigInteger('valide_par')->nullable()->after('document_sujet_id');
            }

            if (! Schema::hasColumn('evaluations', 'motif_rejet')) {
                $table->text('motif_rejet')->nullable()->after('valide_par');
            }
        });

        // Les évaluations existantes (créées avant ce lot, directement par un
        // Directeur via la Resource générique) sont considérées déjà validées :
        // on ne bloque pas rétroactivement la saisie des notes en cours.
        DB::table('evaluations')->whereNull('statut')->orWhere('statut', '')->update(['statut' => 'valide']);
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            foreach (['statut', 'document_sujet_id', 'valide_par', 'motif_rejet'] as $column) {
                if (Schema::hasColumn('evaluations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
