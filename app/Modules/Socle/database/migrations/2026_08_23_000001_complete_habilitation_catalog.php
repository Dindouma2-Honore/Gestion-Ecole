<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['code' => 'module.viescolaire', 'nom' => 'Vie scolaire', 'description' => null, 'categorie' => 'Vie scolaire', 'module_technique' => 'VieScolaire', 'ordre' => 74],
            ['code' => 'viescolaire.sante', 'nom' => 'Santé & infirmerie', 'description' => 'Dossiers santé et visites', 'categorie' => 'Vie scolaire', 'module_technique' => 'VieScolaire', 'ordre' => 75],
            ['code' => 'viescolaire.sorties', 'nom' => 'Sorties des élèves', 'description' => 'Contrôle des sorties', 'categorie' => 'Vie scolaire', 'module_technique' => 'VieScolaire', 'ordre' => 76],
            ['code' => 'logistique.transport', 'nom' => 'Transport scolaire', 'description' => 'Circuits et véhicules', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 83],
            ['code' => 'logistique.cantine', 'nom' => 'Cantine', 'description' => 'Menus et abonnements', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 84],
            ['code' => 'logistique.bibliotheque', 'nom' => 'Bibliothèque', 'description' => 'Catalogue et emprunts', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 85],
        ];

        foreach ($items as $item) {
            DB::table('catalogue_fonctionnalites')->updateOrInsert(
                ['code' => $item['code']],
                [...$item, 'actif' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('catalogue_fonctionnalites')->whereIn('code', [
            'module.viescolaire', 'viescolaire.sante', 'viescolaire.sorties',
            'logistique.transport', 'logistique.cantine', 'logistique.bibliotheque',
        ])->delete();
    }
};
