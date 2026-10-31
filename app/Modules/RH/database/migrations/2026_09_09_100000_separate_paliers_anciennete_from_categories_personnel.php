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
        Schema::create('paliers_anciennete', function (Blueprint $table): void {
            $table->id();
            $table->string('nom')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('anciennete_min_mois');
            $table->unsignedInteger('anciennete_max_mois')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        DB::table('categories_personnel')->where('progression_automatique', true)->orderBy('id')->each(function (object $categorie): void {
            DB::table('paliers_anciennete')->insert([
                'id' => $categorie->id, 'nom' => $categorie->nom, 'description' => $categorie->description,
                'anciennete_min_mois' => $categorie->anciennete_min_mois ?? 0,
                'anciennete_max_mois' => $categorie->anciennete_max_mois, 'actif' => $categorie->actif,
                'created_at' => $categorie->created_at, 'updated_at' => $categorie->updated_at,
            ]);
        });

        Schema::table('employes', function (Blueprint $table): void {
            $table->dropForeign(['categorie_anciennete_id']);
            $table->renameColumn('categorie_anciennete_id', 'palier_anciennete_id');
            $table->foreign('palier_anciennete_id')->references('id')->on('paliers_anciennete')->nullOnDelete();
        });
        Schema::table('grilles_salariales', function (Blueprint $table): void {
            $table->dropForeign(['categorie_personnel_id']);
            $table->dropIndex('grille_salaire_recherche_idx');
            $table->renameColumn('categorie_personnel_id', 'palier_anciennete_id');
            $table->foreign('palier_anciennete_id')->references('id')->on('paliers_anciennete')->restrictOnDelete();
            $table->index(['palier_anciennete_id', 'base_calcul', 'actif'], 'grille_salaire_recherche_idx');
        });

        DB::table('personnel_categorie')->whereIn('categorie_personnel_id', DB::table('categories_personnel')->where('progression_automatique', true)->select('id'))->delete();
        DB::table('categories_personnel')->where('progression_automatique', true)->delete();
        Schema::table('categories_personnel', fn (Blueprint $table) => $table->dropColumn(['anciennete_min_mois', 'anciennete_max_mois', 'progression_automatique']));
    }

    public function down(): void
    {
        Schema::table('categories_personnel', function (Blueprint $table): void {
            $table->unsignedInteger('anciennete_min_mois')->nullable();
            $table->unsignedInteger('anciennete_max_mois')->nullable();
            $table->boolean('progression_automatique')->default(false);
        });
        DB::table('paliers_anciennete')->orderBy('id')->each(fn (object $palier) => DB::table('categories_personnel')->insert([
            'id' => $palier->id, 'nom' => $palier->nom, 'description' => $palier->description,
            'anciennete_min_mois' => $palier->anciennete_min_mois, 'anciennete_max_mois' => $palier->anciennete_max_mois,
            'progression_automatique' => true, 'actif' => $palier->actif, 'created_at' => $palier->created_at, 'updated_at' => $palier->updated_at,
        ]));
        Schema::table('grilles_salariales', function (Blueprint $table): void {
            $table->dropForeign(['palier_anciennete_id']);
            $table->dropIndex('grille_salaire_recherche_idx');
            $table->renameColumn('palier_anciennete_id', 'categorie_personnel_id');
            $table->foreign('categorie_personnel_id')->references('id')->on('categories_personnel')->restrictOnDelete();
            $table->index(['categorie_personnel_id', 'base_calcul', 'actif'], 'grille_salaire_recherche_idx');
        });
        Schema::table('employes', function (Blueprint $table): void {
            $table->dropForeign(['palier_anciennete_id']);
            $table->renameColumn('palier_anciennete_id', 'categorie_anciennete_id');
            $table->foreign('categorie_anciennete_id')->references('id')->on('categories_personnel')->nullOnDelete();
        });
        Schema::dropIfExists('paliers_anciennete');
    }
};
