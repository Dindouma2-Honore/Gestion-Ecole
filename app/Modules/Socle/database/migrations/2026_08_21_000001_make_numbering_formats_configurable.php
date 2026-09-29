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
        Schema::table('formats_numerotation', function (Blueprint $table): void {
            $table->string('libelle')->nullable()->after('type_document');
            $table->string('reinitialisation', 30)->default('jamais')->after('format');
            $table->string('derniere_cle_compteur', 50)->nullable()->after('prochain_numero');
            $table->boolean('a_valider')->default(false)->after('derniere_cle_compteur');
        });

        $maintenant = now();
        DB::table('formats_numerotation')->insertOrIgnore([
            [
                'type_document' => 'facture',
                'libelle' => 'Facture',
                'format' => 'FACT-{ANNEE_SCOLAIRE}-{SEQ:5}',
                'reinitialisation' => 'annee_scolaire',
                'prochain_numero' => 1,
                'a_valider' => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ],
            [
                'type_document' => 'bulletin',
                'libelle' => 'Bulletin scolaire',
                'format' => 'BUL-{ANNEE_SCOLAIRE}-{NIVEAU}-{SEQ:5}',
                'reinitialisation' => 'annee_scolaire',
                'prochain_numero' => 1,
                'a_valider' => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ],
            [
                'type_document' => 'carte_scolaire',
                'libelle' => 'Carte scolaire',
                'format' => 'CARTE-{ANNEE_SCOLAIRE}-{SEQ:6}',
                'reinitialisation' => 'annee_scolaire',
                'prochain_numero' => 1,
                'a_valider' => true,
                'created_at' => $maintenant,
                'updated_at' => $maintenant,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('formats_numerotation')
            ->whereIn('type_document', ['facture', 'bulletin', 'carte_scolaire'])
            ->where('a_valider', true)
            ->delete();

        Schema::table('formats_numerotation', function (Blueprint $table): void {
            $table->dropColumn(['libelle', 'reinitialisation', 'derniere_cle_compteur', 'a_valider']);
        });
    }
};
