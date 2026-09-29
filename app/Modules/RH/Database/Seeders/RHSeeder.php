<?php

namespace App\Modules\RH\Database\Seeders;

use App\Modules\RH\Models\TypePrime;
use Illuminate\Database\Seeder;

class RHSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'rendement', 'libelle' => 'Prime de Rendement / Performance', 'mode_calcul' => 'variable', 'valeur_defaut' => 0.00],
            ['code' => 'anciennete', 'libelle' => 'Prime d\'Ancienneté', 'mode_calcul' => 'pourcentage_base', 'valeur_defaut' => 5.00],
            ['code' => 'responsabilite', 'libelle' => 'Prime de Responsabilité / Fonction', 'mode_calcul' => 'fixe', 'valeur_defaut' => 25000.00],
            ['code' => 'exceptionnelle', 'libelle' => 'Gratification Exceptionnelle', 'mode_calcul' => 'fixe', 'valeur_defaut' => 0.00],
            ['code' => 'fin_annee', 'libelle' => '13ème Mois / Prime de fin d\'année', 'mode_calcul' => 'pourcentage_base', 'valeur_defaut' => 100.00],
        ];

        foreach ($types as $type) {
            TypePrime::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
