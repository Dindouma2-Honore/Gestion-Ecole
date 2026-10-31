<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('config_etablissement', function (Blueprint $table): void {
            $table->string('sigle', 50)->nullable()->after('nom');
            $table->time('heure_ouverture')->nullable()->after('devise');
            $table->time('heure_fermeture')->nullable()->after('heure_ouverture');
            $table->json('jours_ouvrables')->nullable()->after('heure_fermeture');
            $table->unsignedSmallInteger('tolerance_retard_minutes')->default(10)->after('jours_ouvrables');
            $table->string('systeme_notation', 50)->default('numerique')->after('tolerance_retard_minutes');
            $table->decimal('note_maximale', 6, 2)->default(20)->after('systeme_notation');
            $table->unsignedTinyInteger('precision_arrondi')->default(2)->after('note_maximale');
            $table->text('regles_evaluation')->nullable()->after('precision_arrondi');
        });

        Schema::table('periodes', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('annee_scolaire_id')->constrained('periodes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('periodes', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
        Schema::table('config_etablissement', fn (Blueprint $table) => $table->dropColumn([
            'sigle', 'heure_ouverture', 'heure_fermeture', 'jours_ouvrables',
            'tolerance_retard_minutes', 'systeme_notation', 'note_maximale',
            'precision_arrondi', 'regles_evaluation',
        ]));
    }
};
