<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmes', function (Blueprint $t): void {
            $t->unsignedBigInteger('origine_programme_id')->nullable()->index();
            $t->unsignedInteger('version')->default(1);
            $t->string('analyse_statut', 30)->nullable()->index();
            $t->text('analyse_erreur')->nullable();
            $t->timestamp('importe_le')->nullable();
            $t->timestamp('valide_le')->nullable();
            $t->unique(['origine_programme_id', 'version'], 'programme_version_unique');
        });
        Schema::table('programme_chapitres', function (Blueprint $t): void {
            $t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->string('type', 25)->default('chapitre');
        });
        Schema::table('progressions', function (Blueprint $t): void {
            $t->string('etat_avancement', 20)->nullable();
            $t->text('observation')->nullable();
        });
        Schema::create('devoirs_pedagogiques', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('progression_id')->unique()->constrained('progressions')->restrictOnDelete();
            $t->foreignId('seance_id')->constrained('seances')->restrictOnDelete();
            $t->unsignedBigInteger('enseignant_id');
            $t->unsignedBigInteger('classe_id');
            $t->unsignedBigInteger('offre_pedagogique_id');
            $t->unsignedBigInteger('annee_scolaire_id');
            $t->string('type', 30);
            $t->string('consigne', 255);
            $t->text('description')->nullable();
            $t->date('date_remise');
            $t->timestamps();
        });
    }

    public function down(): void
    { /* Historical teaching data is never discarded automatically. */
    }
};
