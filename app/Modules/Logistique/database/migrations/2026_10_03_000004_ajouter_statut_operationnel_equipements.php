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
        if (Schema::hasTable('equipements')) {
            if (! Schema::hasColumn('equipements', 'statut_operationnel')) {
                Schema::table('equipements', function (Blueprint $table): void {
                    $table->string('statut_operationnel', 50)->default('en_stock')->after('etat');
                });
            }

            // Backfill legacy states
            DB::table('equipements')->where('etat', 'a_reparer')->update([
                'statut_operationnel' => 'en_maintenance',
                'etat' => 'mauvais',
            ]);
            DB::table('equipements')->where('etat', 'mis_au_rebut')->update([
                'statut_operationnel' => 'reforme',
                'etat' => 'hors_service',
            ]);
        }

        if (! Schema::hasTable('equipement_historique_statuts')) {
            Schema::create('equipement_historique_statuts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('equipement_id');
                $table->string('ancien_statut', 50)->nullable();
                $table->string('nouveau_statut', 50);
                $table->text('motif')->nullable();
                $table->unsignedBigInteger('effectue_par')->nullable();
                $table->timestamps();

                $table->index('equipement_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('equipement_historique_statuts');

        if (Schema::hasTable('equipements') && Schema::hasColumn('equipements', 'statut_operationnel')) {
            Schema::table('equipements', function (Blueprint $table): void {
                $table->dropColumn('statut_operationnel');
            });
        }
    }
};
