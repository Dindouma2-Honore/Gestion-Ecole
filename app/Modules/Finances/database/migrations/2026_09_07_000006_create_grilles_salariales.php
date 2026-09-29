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
        Schema::table('categories_personnel', function (Blueprint $table): void {
            $table->unsignedInteger('anciennete_min_mois')->nullable()->after('description');
            $table->unsignedInteger('anciennete_max_mois')->nullable()->after('anciennete_min_mois');
            $table->boolean('progression_automatique')->default(false)->after('anciennete_max_mois');
        });

        foreach ([
            ['Junior', 0, 23], ['Confirmé', 24, 59], ['Senior', 60, 119], ['Expert', 120, null],
        ] as [$nom, $min, $max]) {
            DB::table('categories_personnel')->updateOrInsert(
                ['nom' => $nom],
                ['description' => "Catégorie automatique d’ancienneté : {$min} mois et plus", 'anciennete_min_mois' => $min, 'anciennete_max_mois' => $max, 'progression_automatique' => true, 'actif' => true, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        Schema::table('employes', function (Blueprint $table): void {
            $table->foreignId('categorie_anciennete_id')->nullable()->after('poste_administratif_id')->constrained('categories_personnel')->nullOnDelete();
        });

        Schema::create('grilles_salariales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('categorie_personnel_id')->constrained('categories_personnel')->restrictOnDelete();
            $table->enum('base_calcul', ['generale', 'matiere', 'fonction', 'tache']);
            $table->unsignedBigInteger('matiere_id')->nullable();
            $table->foreignId('poste_administratif_id')->nullable()->constrained('postes_administratifs')->nullOnDelete();
            $table->string('tache')->nullable();
            $table->decimal('salaire_base', 12, 2)->default(0);
            $table->decimal('taux_horaire', 12, 2)->default(0);
            $table->date('date_effet')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['categorie_personnel_id', 'base_calcul', 'actif'], 'grille_salaire_recherche_idx');
        });

        Schema::table('contrats', function (Blueprint $table): void {
            $table->foreignId('grille_salariale_id')->nullable()->after('categorie_paie')->constrained('grilles_salariales')->nullOnDelete();
            $table->unsignedBigInteger('matiere_paie_id')->nullable()->after('grille_salariale_id');
            $table->string('tache_administrative')->nullable()->after('matiere_paie_id');
        });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('grille_salariale_id');
            $table->dropColumn(['matiere_paie_id', 'tache_administrative']);
        });
        Schema::dropIfExists('grilles_salariales');
        Schema::table('employes', fn (Blueprint $table) => $table->dropConstrainedForeignId('categorie_anciennete_id'));
        Schema::table('categories_personnel', fn (Blueprint $table) => $table->dropColumn(['anciennete_min_mois', 'anciennete_max_mois', 'progression_automatique']));
    }
};
