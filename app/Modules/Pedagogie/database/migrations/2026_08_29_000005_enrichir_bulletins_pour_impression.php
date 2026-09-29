<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulletins', function (Blueprint $table): void {
            // Statistiques de la classe, figées au moment de la génération
            // (comme sur un vrai bulletin imprimé : elles ne doivent pas
            // bouger rétroactivement si d'autres notes sont saisies après).
            if (! Schema::hasColumn('bulletins', 'moyenne_classe_generale')) {
                $table->decimal('moyenne_classe_generale', 5, 2)->nullable()->after('effectif_classe');
            }
            if (! Schema::hasColumn('bulletins', 'moyenne_plus_forte')) {
                $table->decimal('moyenne_plus_forte', 5, 2)->nullable()->after('moyenne_classe_generale');
            }
            if (! Schema::hasColumn('bulletins', 'moyenne_plus_faible')) {
                $table->decimal('moyenne_plus_faible', 5, 2)->nullable()->after('moyenne_plus_forte');
            }
            if (! Schema::hasColumn('bulletins', 'appreciation_generale')) {
                $table->string('appreciation_generale', 50)->nullable()->after('moyenne_plus_faible');
            }
            if (! Schema::hasColumn('bulletins', 'mention')) {
                $table->string('mention', 80)->nullable()->after('appreciation_generale');
            }
            // Instantané du nom de l'élève / de la classe au moment de
            // l'édition : un bulletin déjà imprimé ne doit pas changer si le
            // nom est corrigé plus tard côté Scolarité.
            if (! Schema::hasColumn('bulletins', 'nom_eleve')) {
                $table->string('nom_eleve', 150)->nullable()->after('mention');
            }
            if (! Schema::hasColumn('bulletins', 'nom_classe')) {
                $table->string('nom_classe', 100)->nullable()->after('nom_eleve');
            }
        });

        Schema::table('bulletin_matieres', function (Blueprint $table): void {
            if (! Schema::hasColumn('bulletin_matieres', 'rang')) {
                $table->unsignedSmallInteger('rang')->nullable()->after('moyenne');
            }
            if (! Schema::hasColumn('bulletin_matieres', 'moyenne_classe')) {
                $table->decimal('moyenne_classe', 5, 2)->nullable()->after('rang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bulletin_matieres', function (Blueprint $table): void {
            foreach (['rang', 'moyenne_classe'] as $column) {
                if (Schema::hasColumn('bulletin_matieres', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('bulletins', function (Blueprint $table): void {
            foreach (['moyenne_classe_generale', 'moyenne_plus_forte', 'moyenne_plus_faible', 'appreciation_generale', 'mention', 'nom_eleve', 'nom_classe'] as $column) {
                if (Schema::hasColumn('bulletins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
