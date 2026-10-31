<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->unsignedBigInteger('annulation_visee_par')->nullable()->after('annulation_demandee_le');
            $table->dateTime('annulation_visee_le')->nullable()->after('annulation_visee_par');
            $table->text('visa_comptable_notes')->nullable()->after('annulation_visee_le');

            $table->foreign('annulation_visee_par')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('transferts_affectations', function (Blueprint $table): void {
            $table->decimal('montant_scolarite_origine', 10, 2)->nullable()->after('motif');
            $table->decimal('montant_scolarite_destination', 10, 2)->nullable()->after('montant_scolarite_origine');
            $table->decimal('solde_avant_transfert', 10, 2)->nullable()->after('montant_scolarite_destination');
            $table->decimal('solde_apres_transfert', 10, 2)->nullable()->after('solde_avant_transfert');
        });
    }

    public function down(): void
    {
        Schema::table('transferts_affectations', function (Blueprint $table): void {
            $table->dropColumn([
                'montant_scolarite_origine',
                'montant_scolarite_destination',
                'solde_avant_transfert',
                'solde_apres_transfert',
            ]);
        });

        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->dropForeign(['annulation_visee_par']);
            $table->dropColumn([
                'annulation_visee_par',
                'annulation_visee_le',
                'visa_comptable_notes',
            ]);
        });
    }
};
