<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('types_conges', function (Blueprint $table): void {
            $table->boolean('remunere')->default(true)->after('description');
            $table->string('impact_paie', 30)->default('aucun')->after('remunere');
            $table->boolean('decompte_solde')->default(false)->after('impact_paie');
            $table->boolean('justificatif_obligatoire')->default(false)->after('decompte_solde');
            $table->boolean('validation_requise')->default(true)->after('justificatif_obligatoire');
            $table->string('methode_deduction', 50)->nullable()->after('validation_requise');
        });

        DB::table('types_conges')->where('code', 'conge_annuel')->update([
            'remunere' => true,
            'impact_paie' => 'aucun',
            'decompte_solde' => true,
            'justificatif_obligatoire' => false,
            'validation_requise' => true,
            'methode_deduction' => null,
        ]);

        DB::table('types_conges')->updateOrInsert(
            ['code' => 'conge-sans-solde'],
            [
                'nom' => 'Congé sans solde',
                'description' => null,
                'remunere' => false,
                'impact_paie' => 'deduction',
                'decompte_solde' => false,
                'justificatif_obligatoire' => false,
                'validation_requise' => true,
                'methode_deduction' => 'jours_non_remuneres',
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        Schema::table('types_conges', function (Blueprint $table): void {
            $table->dropColumn([
                'remunere',
                'impact_paie',
                'decompte_solde',
                'justificatif_obligatoire',
                'validation_requise',
                'methode_deduction',
            ]);
        });
    }
};
