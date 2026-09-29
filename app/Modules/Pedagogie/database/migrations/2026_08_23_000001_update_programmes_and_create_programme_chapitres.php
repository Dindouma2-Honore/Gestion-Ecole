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
        if (Schema::hasTable('programmes')) {
            Schema::table('programmes', function (Blueprint $table): void {
                if (! Schema::hasColumn('programmes', 'classe_id')) {
                    $table->unsignedBigInteger('classe_id')->nullable()->after('annee_scolaire_id');
                }

                if (! Schema::hasColumn('programmes', 'source')) {
                    $table->string('source', 20)->default('officiel')->after('classe_id');
                }

                if (! Schema::hasColumn('programmes', 'enseignant_id')) {
                    $table->unsignedBigInteger('enseignant_id')->nullable()->after('source');
                }

                if (! Schema::hasColumn('programmes', 'document_source_id')) {
                    $table->unsignedBigInteger('document_source_id')->nullable()->after('enseignant_id');
                }

                if (! Schema::hasColumn('programmes', 'salle_id')) {
                    $table->unsignedBigInteger('salle_id')->nullable()->after('document_source_id');
                }

                if (! Schema::hasColumn('programmes', 'valide_par')) {
                    $table->unsignedBigInteger('valide_par')->nullable()->after('salle_id');
                }

                if (! Schema::hasColumn('programmes', 'motif_rejet')) {
                    $table->text('motif_rejet')->nullable()->after('valide_par');
                }
            });

            // Set default statut and source for existing records
            DB::table('programmes')
                ->whereNull('source')
                ->orWhere('source', '')
                ->update(['source' => 'officiel']);

            DB::table('programmes')
                ->where('source', 'officiel')
                ->whereIn('statut', ['brouillon', 'publie'])
                ->update(['statut' => 'valide']);
        }

        if (! Schema::hasTable('programme_chapitres')) {
            Schema::create('programme_chapitres', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('programme_id')->constrained('programmes')->cascadeOnDelete();
                $table->string('titre', 200);
                $table->unsignedSmallInteger('ordre')->default(0);
                $table->text('objectifs_pedagogiques')->nullable();
                $table->unsignedBigInteger('periode_prevue_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_chapitres');

        if (Schema::hasTable('programmes')) {
            Schema::table('programmes', function (Blueprint $table): void {
                $columns = ['classe_id', 'source', 'enseignant_id', 'document_source_id', 'salle_id', 'valide_par', 'motif_rejet'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('programmes', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
