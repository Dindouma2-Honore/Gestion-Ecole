<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avances_salaires', function (Blueprint $table): void {
            $table->foreignId('demande_par')->nullable()->after('motif')->constrained('users')->nullOnDelete();
            $table->boolean('derogation_plafond')->default(false)->after('montant_deja_deduit');
            $table->text('motif_derogation')->nullable()->after('derogation_plafond');
            $table->timestamp('validee_le')->nullable()->after('valide_par');
            $table->timestamp('decaissee_le')->nullable()->after('validee_le');
            $table->index(['employe_id', 'statut', 'date_demande'], 'avances_employe_statut_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('avances_salaires', function (Blueprint $table): void {
            $table->dropIndex('avances_employe_statut_date_index');
            $table->dropConstrainedForeignId('demande_par');
            $table->dropColumn(['derogation_plafond', 'motif_derogation', 'validee_le', 'decaissee_le']);
        });
    }
};
