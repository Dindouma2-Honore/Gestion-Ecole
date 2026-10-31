<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['assiduite.dashboard', 'Vue d’assiduité', 'Consulter les indicateurs consolidés'],
            ['assiduite.eleves', 'Présences des élèves', 'Consulter les présences et appels pédagogiques'],
            ['assiduite.absences', 'Absences et retards', 'Contrôler les absences, retards et départs anticipés'],
            ['assiduite.personnel', 'Pointage du personnel', 'Consulter l’assiduité du personnel'],
            ['assiduite.biometrie', 'Pointages biométriques', 'Consulter et importer les pointages bruts'],
            ['assiduite.justifications', 'Justifications', 'Soumettre et traiter les justificatifs'],
            ['assiduite.anomalies', 'Anomalies', 'Affecter, corriger et clôturer les anomalies'],
            ['assiduite.rapports', 'Rapports d’assiduité', 'Consulter et exporter les rapports'],
        ];

        foreach ($items as $index => [$code, $nom, $description]) {
            DB::table('catalogue_fonctionnalites')->updateOrInsert(
                ['code' => $code],
                [
                    'nom' => $nom,
                    'description' => $description,
                    'categorie' => 'Assiduité',
                    'module_technique' => 'Assiduite',
                    'ordre' => 84 + $index,
                    'actif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('catalogue_fonctionnalites')->whereIn('code', [
            'assiduite.dashboard',
            'assiduite.eleves',
            'assiduite.absences',
            'assiduite.personnel',
            'assiduite.biometrie',
            'assiduite.justifications',
            'assiduite.anomalies',
            'assiduite.rapports',
        ])->delete();
    }
};
