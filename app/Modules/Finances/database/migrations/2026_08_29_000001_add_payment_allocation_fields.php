<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('types_frais_recurrents', function (Blueprint $table): void {
            $table->enum('nature', ['inscription', 'scolarite', 'autre'])->default('autre')->after('nom');
            $table->decimal('ratio_tranche_1', 5, 2)->default(50)->after('montant');
        });

        DB::table('types_frais_recurrents')->orderBy('id')->get()->each(function (object $type): void {
            $nom = Str::slug((string) $type->nom);
            $nature = $nom === 'inscription' ? 'inscription' : ($nom === 'scolarite' ? 'scolarite' : 'autre');
            DB::table('types_frais_recurrents')->where('id', $type->id)->update(['nature' => $nature]);
        });

        Schema::table('catalogue_frais_divers', function (Blueprint $table): void {
            $table->string('categorie', 50)->change();
            $table->enum('periodicite', ['unique', 'mensuelle', 'trimestrielle', 'annuelle'])->default('unique')->after('categorie');
        });

        Schema::table('paiements', function (Blueprint $table): void {
            $table->unsignedBigInteger('inscription_id')->nullable()->after('annee_scolaire_id');
            $table->uuid('versement_reference')->nullable()->after('inscription_id');
            $table->string('rubrique', 100)->nullable()->after('versement_reference');
            $table->foreignId('frais_divers_eleve_id')->nullable()->after('rubrique')->constrained('frais_divers_eleves')->restrictOnDelete();
            $table->index(['inscription_id', 'rubrique', 'statut'], 'paiements_repartition_index');
            $table->index('versement_reference');
        });

        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->string('rubrique', 120)->nullable()->after('type');
            $table->index(['session_caisse_id', 'type', 'rubrique'], 'mouvements_caisse_rubrique_index');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_caisse', function (Blueprint $table): void {
            $table->dropIndex('mouvements_caisse_rubrique_index');
            $table->dropColumn('rubrique');
        });
        Schema::table('paiements', function (Blueprint $table): void {
            $table->dropIndex('paiements_repartition_index');
            $table->dropIndex(['versement_reference']);
            $table->dropConstrainedForeignId('frais_divers_eleve_id');
            $table->dropColumn(['inscription_id', 'versement_reference', 'rubrique']);
        });
        Schema::table('catalogue_frais_divers', function (Blueprint $table): void {
            $table->dropColumn('periodicite');
            $table->enum('categorie', ['examen', 'tenue', 'transport', 'fourniture', 'autre'])->change();
        });
        Schema::table('types_frais_recurrents', fn (Blueprint $table) => $table->dropColumn(['nature', 'ratio_tranche_1']));
    }
};
