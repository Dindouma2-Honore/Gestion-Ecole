<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures_preinscription', function (Blueprint $table): void {
            $table->decimal('montant_versement_prevu', 12, 2)->nullable()->after('montant_total');
        });
    }

    public function down(): void
    {
        Schema::table('factures_preinscription', function (Blueprint $table): void {
            $table->dropColumn('montant_versement_prevu');
        });
    }
};
