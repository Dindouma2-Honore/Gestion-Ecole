<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le matricule est désormais généré à la création de l'élève (Eleve::booted,
 * via EleveService::genererMatricule()), plus à l'inscription — voir Module
 * 5 §1. Sa numérotation passe par `formats_numerotation` (Socle,
 * ParametrageServiceContract::genererNumero('matricule_eleve', ...) — voir
 * 2026_09_01_000013_seed_formats_numerotation_scolarite), le contrat de
 * numérotation étant maintenant livré et vérifié : les compteurs locaux
 * n'ont donc plus lieu d'être.
 *
 * On supprime les deux tables :
 * - `compteur_matricules` (ancien compteur local, migration
 *   0001_01_02_000005) ;
 * - `compteurs_matricules` (avec un "s", migration 0001_01_02_000004) — une
 *   table orpheline : `CompteurMatricule` n'a jamais eu de $table
 *   personnalisé, son vrai nom de table Eloquent était `compteur_matricules`
 *   — `compteurs_matricules` n'a jamais été utilisée par aucun modèle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('compteurs_matricules');
        Schema::dropIfExists('compteur_matricules');
    }

    public function down(): void
    {
        Schema::create('compteur_matricules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('annee_scolaire_id')->unique();
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('compteurs_matricules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('annee_scolaire_id')->unique();
            $table->unsignedInteger('dernier_numero')->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }
};
