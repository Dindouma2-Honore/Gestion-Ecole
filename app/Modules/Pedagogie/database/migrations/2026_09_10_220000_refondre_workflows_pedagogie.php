<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('emplois_du_temps', function (Blueprint $table): void {
            $table->string('statut', 20)->default('brouillon')->after('actif')->index();
            $table->unsignedBigInteger('publie_par')->nullable()->after('statut');
            $table->timestamp('publie_le')->nullable()->after('publie_par');
        });

        DB::table('emplois_du_temps')->where('actif', true)->update(['statut' => 'publie']);

        Schema::table('programme_chapitres', function (Blueprint $table): void {
            $table->text('objectif')->nullable()->after('titre');
            $table->longText('contenu')->nullable()->after('objectif');
            $table->decimal('volume_horaire_prevu', 6, 2)->nullable()->after('contenu');
            $table->text('ressources')->nullable()->after('periode_prevue_id');
            $table->string('statut', 20)->default('a_faire')->after('ressources');
        });

        Schema::table('notes', function (Blueprint $table): void {
            $table->string('statut', 20)->default('brouillon')->after('verrouillee')->index();
            $table->unsignedBigInteger('soumis_par')->nullable()->after('statut');
            $table->timestamp('soumis_le')->nullable()->after('soumis_par');
            $table->unsignedBigInteger('valide_par')->nullable()->after('soumis_le');
            $table->timestamp('valide_le')->nullable()->after('valide_par');
        });

        DB::table('notes')->where('verrouillee', true)->update(['statut' => 'verrouille']);

        Schema::table('presences', function (Blueprint $table): void {
            $table->string('statut_justification', 20)->nullable()->after('document_justificatif_id')->index();
            $table->unsignedBigInteger('justification_verifiee_par')->nullable()->after('statut_justification');
            $table->timestamp('justification_verifiee_le')->nullable()->after('justification_verifiee_par');
        });

        Schema::create('conseils_classe', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('classe_id');
            $table->unsignedBigInteger('periode_id');
            $table->unsignedInteger('effectif')->default(0);
            $table->decimal('moyenne_classe', 6, 2)->nullable();
            $table->decimal('taux_reussite', 6, 2)->nullable();
            $table->date('date_conseil')->nullable();
            $table->string('statut', 20)->default('planifie');
            $table->unsignedBigInteger('valide_par')->nullable();
            $table->timestamp('valide_le')->nullable();
            $table->timestamps();
            $table->unique(['classe_id', 'periode_id']);
        });

        Schema::create('decisions_conseil', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conseil_classe_id')->constrained('conseils_classe')->cascadeOnDelete();
            $table->unsignedBigInteger('eleve_id');
            $table->string('decision');
            $table->timestamps();
            $table->unique(['conseil_classe_id', 'eleve_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions_conseil');
        Schema::dropIfExists('conseils_classe');
        Schema::table('presences', fn (Blueprint $table) => $table->dropColumn(['statut_justification', 'justification_verifiee_par', 'justification_verifiee_le']));
        Schema::table('notes', fn (Blueprint $table) => $table->dropColumn(['statut', 'soumis_par', 'soumis_le', 'valide_par', 'valide_le']));
        Schema::table('programme_chapitres', fn (Blueprint $table) => $table->dropColumn(['objectif', 'contenu', 'volume_horaire_prevu', 'ressources', 'statut']));
        Schema::table('emplois_du_temps', fn (Blueprint $table) => $table->dropColumn(['statut', 'publie_par', 'publie_le']));
    }
};
