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
        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->text('motif_annulation')->nullable()->after('statut');
            $table->foreignId('annulation_demandee_par')->nullable()->after('motif_annulation')->constrained('users')->nullOnDelete();
            $table->timestamp('annulation_demandee_le')->nullable()->after('annulation_demandee_par');
            $table->foreignId('annulation_validee_par')->nullable()->after('annulation_demandee_le')->constrained('users')->nullOnDelete();
            $table->timestamp('annulation_validee_le')->nullable()->after('annulation_validee_par');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE inscriptions MODIFY statut ENUM('en_cours', 'en_attente_versement', 'active', 'validee', 'en_attente_annulation', 'annulee') NOT NULL DEFAULT 'en_cours'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('inscriptions')->where('statut', 'en_attente_annulation')->update(['statut' => 'active']);
            DB::statement("ALTER TABLE inscriptions MODIFY statut ENUM('en_cours', 'en_attente_versement', 'active', 'validee', 'annulee') NOT NULL DEFAULT 'en_cours'");
        }

        Schema::table('inscriptions', function (Blueprint $table): void {
            $table->dropForeign(['annulation_demandee_par']);
            $table->dropForeign(['annulation_validee_par']);
            $table->dropColumn([
                'motif_annulation',
                'annulation_demandee_par',
                'annulation_demandee_le',
                'annulation_validee_par',
                'annulation_validee_le',
            ]);
        });
    }
};
