<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Socle\Models\TemplateDocument;
use Illuminate\Database\Seeder;

class TemplateDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'BULLETIN_PAIE',
                'nom' => 'Modèle de Bulletin de Paie Standard',
                'type' => 'pdf',
                'fichier_template' => 'rh::pdf.bulletin-paie',
                'actif' => true,
                'version' => 1,
                'contenu_html' => 'Template RH Bulletin de Paie',
                'variables_disponibles' => ['bulletin', 'employe', 'contrat', 'salaire_base', 'net_a_payer', 'annee'],
            ],
            [
                'code' => 'FACTURE_STANDARD',
                'nom' => 'Modèle de Facture Scolaire Standard',
                'type' => 'pdf',
                'fichier_template' => 'finances::pdf.facture',
                'actif' => true,
                'version' => 1,
                'contenu_html' => 'Template Finances Facture Standard',
                'variables_disponibles' => ['facture', 'reference', 'parent_nom', 'eleve_nom', 'lignes', 'montant_total'],
            ],
            [
                'code' => 'CONTRAT_TRAVAIL',
                'nom' => 'Modèle de Contrat de Travail Personnel',
                'type' => 'pdf',
                'fichier_template' => 'rh::pdf.contrat',
                'actif' => true,
                'version' => 1,
                'contenu_html' => 'Template RH Contrat de Travail',
                'variables_disponibles' => ['contrat', 'employe', 'type', 'date_debut', 'salaire_base'],
            ],
            [
                'code' => 'LISTE_ELEVES',
                'nom' => 'Modèle de Liste des Élèves par Classe',
                'type' => 'pdf',
                'fichier_template' => 'scolarite::pdf.liste-eleves',
                'actif' => true,
                'version' => 1,
                'contenu_html' => 'Template Scolarité Liste des Élèves',
                'variables_disponibles' => ['eleves', 'classe', 'niveau', 'annee'],
            ],
        ];

        foreach ($templates as $data) {
            TemplateDocument::query()->updateOrCreate(
                ['code' => $data['code']],
                $data,
            );
        }
    }
}
