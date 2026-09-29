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
        Schema::table('matieres', function (Blueprint $table): void {
            $table->decimal('coefficient_defaut', 5, 2)->default(1)->after('code');
            $table->unsignedBigInteger('niveau_id')->nullable()->after('coefficient_defaut');
            $table->index(['niveau_id', 'actif']);
        });
        DB::table('matieres')->update(['coefficient_defaut' => DB::raw('coefficient')]);

        Schema::table('affectations_pedagogiques', function (Blueprint $table): void {
            $table->decimal('coefficient', 5, 2)->nullable()->after('annee_scolaire_id');
        });

        Schema::table('notes', function (Blueprint $table): void {
            $table->decimal('valeur', 8, 2)->nullable()->change();
            $table->boolean('absent')->default(false)->after('valeur');
            $table->boolean('verrouillee')->default(false)->after('absent');
            $table->text('motif_correction')->nullable()->after('verrouillee');
            $table->unsignedBigInteger('saisie_par')->nullable()->after('motif_correction');
        });

        Schema::table('presences', function (Blueprint $table): void {
            $table->text('motif')->nullable()->after('statut');
        });

        Schema::table('bulletins', function (Blueprint $table): void {
            $table->text('decision_conseil')->nullable()->after('appreciation_generale');
            $table->unsignedInteger('version')->default(1)->after('statut');
            $table->unsignedBigInteger('soumis_par')->nullable()->after('version');
            $table->timestamp('soumis_le')->nullable()->after('soumis_par');
            $table->unsignedBigInteger('valide_par')->nullable()->after('soumis_le');
            $table->timestamp('valide_le')->nullable()->after('valide_par');
            $table->unsignedBigInteger('publie_par')->nullable()->after('valide_le');
            $table->timestamp('publie_le')->nullable()->after('publie_par');
        });

        Schema::create('bulletin_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('donnees');
            $table->text('motif')->nullable();
            $table->unsignedBigInteger('cree_par')->nullable();
            $table->timestamps();
            $table->unique(['bulletin_id', 'version']);
        });

        Schema::create('discipline_eleves', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('eleve_id');
            $table->unsignedBigInteger('classe_id')->nullable();
            $table->unsignedBigInteger('annee_scolaire_id');
            $table->date('date_incident');
            $table->string('type_incident', 100);
            $table->string('gravite', 50);
            $table->text('description');
            $table->text('mesure_prise')->nullable();
            $table->boolean('confidentiel')->default(true);
            $table->unsignedBigInteger('enregistre_par')->nullable();
            $table->timestamps();
            $table->index(['eleve_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_eleves');
        Schema::dropIfExists('bulletin_versions');
        Schema::table('bulletins', fn (Blueprint $table) => $table->dropColumn(['decision_conseil', 'version', 'soumis_par', 'soumis_le', 'valide_par', 'valide_le', 'publie_par', 'publie_le']));
        Schema::table('presences', fn (Blueprint $table) => $table->dropColumn('motif'));
        Schema::table('notes', function (Blueprint $table): void {
            $table->dropColumn(['absent', 'verrouillee', 'motif_correction', 'saisie_par']);
            $table->decimal('valeur', 8, 2)->nullable(false)->change();
        });
        Schema::table('affectations_pedagogiques', fn (Blueprint $table) => $table->dropColumn('coefficient'));
        Schema::table('matieres', function (Blueprint $table): void {
            $table->dropIndex(['niveau_id', 'actif']);
            $table->dropColumn(['coefficient_defaut', 'niveau_id']);
        });
    }
};
