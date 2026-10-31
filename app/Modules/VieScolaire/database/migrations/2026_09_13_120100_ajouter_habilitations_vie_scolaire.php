<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['viescolaire.sorties', 'Sorties des élèves', 'Consulter et enregistrer les sorties'],
            ['viescolaire.visiteurs', 'Visiteurs', 'Gérer le registre des visiteurs'],
            ['viescolaire.sante', 'Santé — consultation', 'Consulter les dossiers de santé'],
            ['viescolaire.sante_modifier', 'Santé — modification', 'Modifier les données médicales'],
            ['viescolaire.infirmerie', 'Infirmerie', 'Enregistrer les visites et soins'],
            ['viescolaire.incidents', 'Incidents', 'Signaler et suivre les incidents'],
            ['viescolaire.reclamations', 'Réclamations', 'Affecter et traiter les réclamations'],
            ['viescolaire.calendrier', 'Calendrier institutionnel', 'Gérer les fermetures et journées particulières'],
            ['viescolaire.evenements', 'Événements scolaires', 'Planifier les grands événements'],
            ['viescolaire.rapports', 'Rapports Vie scolaire', 'Consulter les statistiques et historiques'],
        ];
        foreach ($items as $index => [$code, $nom, $description]) {
            DB::table('catalogue_fonctionnalites')->updateOrInsert(['code' => $code], ['nom' => $nom, 'description' => $description, 'categorie' => 'Vie scolaire', 'module_technique' => 'VieScolaire', 'ordre' => 74 + $index, 'actif' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('catalogue_fonctionnalites')->whereIn('code', ['viescolaire.sorties', 'viescolaire.visiteurs', 'viescolaire.sante', 'viescolaire.sante_modifier', 'viescolaire.infirmerie', 'viescolaire.incidents', 'viescolaire.reclamations', 'viescolaire.calendrier', 'viescolaire.evenements', 'viescolaire.rapports'])->delete();
    }
};
