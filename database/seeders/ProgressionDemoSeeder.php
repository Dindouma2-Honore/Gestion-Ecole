<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Pedagogie\Enums\StatutProgrammeEnum;
use App\Modules\Pedagogie\Enums\StatutProgressionElementEnum;
use App\Modules\Pedagogie\Enums\TypeDevoirEnum;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProgressionDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $annee = DB::table('annees_scolaires')->where('statut', 'active')->first();
            if (! $annee) {
                $anneeId = DB::table('annees_scolaires')->insertGetId([
                    'libelle' => '2026-2027',
                    'date_debut' => '2026-09-01',
                    'date_fin' => '2027-07-31',
                    'statut' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $anneeId = $annee->id;
            }

            // =========================================================================
            // 1. SALLES DE CLASSE & LOCAUX (MODULE LOGISTIQUE / SCOLARITE)
            // =========================================================================
            $sallesData = [
                // Bâtiment A — Secondaire (Premier cycle)
                ['code' => 'S101', 'nom' => 'Salle 101 (6e A)', 'batiment' => 'Bâtiment A', 'zone' => 'RDC', 'type' => 'classe', 'capacite' => 40, 'etat' => 'bon'],
                ['code' => 'S102', 'nom' => 'Salle 102 (6e B)', 'batiment' => 'Bâtiment A', 'zone' => 'RDC', 'type' => 'classe', 'capacite' => 40, 'etat' => 'bon'],
                ['code' => 'S103', 'nom' => 'Salle 103 (5e A)', 'batiment' => 'Bâtiment A', 'zone' => '1er Étage', 'type' => 'classe', 'capacite' => 38, 'etat' => 'bon'],
                ['code' => 'S104', 'nom' => 'Salle 104 (4e A)', 'batiment' => 'Bâtiment A', 'zone' => '1er Étage', 'type' => 'classe', 'capacite' => 38, 'etat' => 'bon'],
                ['code' => 'S105', 'nom' => 'Salle 105 (3e A)', 'batiment' => 'Bâtiment A', 'zone' => '2e Étage', 'type' => 'classe', 'capacite' => 35, 'etat' => 'bon'],

                // Bâtiment B — Secondaire (Second cycle)
                ['code' => 'S201', 'nom' => 'Salle 201 (2nde A)', 'batiment' => 'Bâtiment B', 'zone' => 'RDC', 'type' => 'classe', 'capacite' => 35, 'etat' => 'bon'],
                ['code' => 'S202', 'nom' => 'Salle 202 (1ère A)', 'batiment' => 'Bâtiment B', 'zone' => '1er Étage', 'type' => 'classe', 'capacite' => 35, 'etat' => 'bon'],
                ['code' => 'S203', 'nom' => 'Salle 203 (Terminale A)', 'batiment' => 'Bâtiment B', 'zone' => '1er Étage', 'type' => 'classe', 'capacite' => 35, 'etat' => 'bon'],

                // Bâtiment Primaire
                ['code' => 'SP1', 'nom' => 'Salle P1 (CP)', 'batiment' => 'Bâtiment Primaire', 'zone' => 'Aile Ouest', 'type' => 'classe', 'capacite' => 30, 'etat' => 'bon'],
                ['code' => 'SP2', 'nom' => 'Salle P2 (CE1/CE2)', 'batiment' => 'Bâtiment Primaire', 'zone' => 'Aile Ouest', 'type' => 'classe', 'capacite' => 30, 'etat' => 'bon'],
                ['code' => 'SP3', 'nom' => 'Salle P3 (CM1)', 'batiment' => 'Bâtiment Primaire', 'zone' => 'Aile Est', 'type' => 'classe', 'capacite' => 32, 'etat' => 'bon'],
                ['code' => 'SP4', 'nom' => 'Salle P4 (CM2)', 'batiment' => 'Bâtiment Primaire', 'zone' => 'Aile Est', 'type' => 'classe', 'capacite' => 32, 'etat' => 'bon'],

                // Bâtiment Maternelle
                ['code' => 'SM1', 'nom' => 'Salle M1 (Petite Section)', 'batiment' => 'Maternelle', 'zone' => 'Cour Maternelle', 'type' => 'classe', 'capacite' => 25, 'etat' => 'bon'],
                ['code' => 'SM2', 'nom' => 'Salle M2 (Grande Section)', 'batiment' => 'Maternelle', 'zone' => 'Cour Maternelle', 'type' => 'classe', 'capacite' => 25, 'etat' => 'bon'],

                // Salles Spécialisées
                ['code' => 'LAB-SCI', 'nom' => 'Laboratoire de Sciences', 'batiment' => 'Bâtiment Scientifique', 'zone' => 'RDC', 'type' => 'laboratoire', 'capacite' => 32, 'etat' => 'bon'],
                ['code' => 'INFO-1', 'nom' => 'Salle Informatique', 'batiment' => 'Bâtiment Principal', 'zone' => '1er Étage', 'type' => 'autre', 'capacite' => 30, 'etat' => 'bon'],
                ['code' => 'POLY-1', 'nom' => 'Salle Polyvalente', 'batiment' => 'Bâtiment Principal', 'zone' => 'RDC', 'type' => 'autre', 'capacite' => 150, 'etat' => 'bon'],
            ];

            $sallesIds = [];
            foreach ($sallesData as $salle) {
                DB::table('salles')->updateOrInsert(
                    ['code' => $salle['code']],
                    [
                        'nom' => $salle['nom'],
                        'batiment' => $salle['batiment'],
                        'zone' => $salle['zone'],
                        'type' => $salle['type'],
                        'capacite' => $salle['capacite'],
                        'etat' => $salle['etat'],
                        'disponible' => true,
                        'observations' => 'Salle opérationnelle et équipée pour les cours.',
                    ]
                );
                $sallesIds[$salle['code']] = (int) DB::table('salles')->where('code', $salle['code'])->value('id');
            }

            // =========================================================================
            // 2. CLASSES : 6e A et création de 6e B
            // =========================================================================
            $classe6eA = DB::table('classes')->where('code', '6E-A')->where('annee_scolaire_id', $anneeId)->first();
            if (! $classe6eA) {
                $classe6eAId = DB::table('classes')->insertGetId([
                    'nom' => '6e A',
                    'code' => '6E-A',
                    'niveau_id' => 10,
                    'section_id' => 1,
                    'annee_scolaire_id' => $anneeId,
                    'salle_principale_id' => $sallesIds['S101'],
                    'capacite_max' => 40,
                    'statut' => 'active',
                    'frais' => '135000.00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $classe6eAId = $classe6eA->id;
                DB::table('classes')->where('id', $classe6eAId)->update(['salle_principale_id' => $sallesIds['S101']]);
            }

            $classe6eB = DB::table('classes')->where('code', '6E-B')->where('annee_scolaire_id', $anneeId)->first();
            if (! $classe6eB) {
                $classe6eBId = DB::table('classes')->insertGetId([
                    'nom' => '6e B',
                    'code' => '6E-B',
                    'niveau_id' => 10,
                    'section_id' => 1,
                    'annee_scolaire_id' => $anneeId,
                    'salle_principale_id' => $sallesIds['S102'],
                    'capacite_max' => 40,
                    'statut' => 'active',
                    'frais' => '135000.00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $classe6eBId = $classe6eB->id;
                DB::table('classes')->where('id', $classe6eBId)->update(['salle_principale_id' => $sallesIds['S102']]);
            }

            // =========================================================================
            // 3. UTILISATEURS & PROFILS ENSEIGNANTS
            // =========================================================================
            // Enseignant Secondaire Demo (déjà existant)
            $userSecondaire = User::where('email', 'secondaire.demo@ambassadors.local')->first();
            $ensSecondaireId = (int) DB::table('enseignants')->where('employe_id', function ($q) use ($userSecondaire) {
                $q->select('id')->from('employes')->where('user_id', $userSecondaire?->id)->limit(1);
            })->value('id');

            // Profil Enseignant pour le compte AG (admin@ambassadors.com)
            $userAdmin = User::where('email', 'admin@ambassadors.com')->first();
            if ($userAdmin) {
                $employeAdmin = DB::table('employes')->where('user_id', $userAdmin->id)->first();
                if (! $employeAdmin) {
                    $employeAdminId = DB::table('employes')->insertGetId([
                        'matricule' => 'DIR-AG',
                        'user_id' => $userAdmin->id,
                        'nom' => 'Administrateur',
                        'prenom' => 'Général',
                        'email' => $userAdmin->email,
                        'poste' => 'Directeur Général & Enseignant',
                        'date_embauche' => '2026-09-01',
                        'statut' => 'actif',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $employeAdminId = $employeAdmin->id;
                }

                $enseignantAdmin = DB::table('enseignants')->where('employe_id', $employeAdminId)->first();
                if (! $enseignantAdmin) {
                    $ensAdminId = DB::table('enseignants')->insertGetId([
                        'employe_id' => $employeAdminId,
                        'specialite' => 'Mathématiques & Sciences',
                        'statut_contractuel' => 'titulaire',
                        'charge_horaire_hebdo' => 18,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $ensAdminId = $enseignantAdmin->id;
                }
            } else {
                $ensAdminId = $ensSecondaireId;
            }

            // =========================================================================
            // 4. MATIÈRES & OFFRES PÉDAGOGIQUES
            // =========================================================================
            $matiereMathId = (int) DB::table('matieres')->where('code', 'MATH-DEMO')->value('id');
            if (! $matiereMathId) {
                $categorieId = (int) DB::table('categories_matieres')->value('id');
                $matiereMathId = DB::table('matieres')->insertGetId([
                    'nom' => 'Mathématiques',
                    'code' => 'MATH-DEMO',
                    'categorie_matiere_id' => $categorieId,
                    'coefficient' => 4,
                    'coefficient_defaut' => 4,
                    'actif' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $matiereFrId = (int) DB::table('matieres')->where('code', 'FR-DEMO')->value('id');
            if (! $matiereFrId) {
                $categorieId = (int) DB::table('categories_matieres')->value('id');
                $matiereFrId = DB::table('matieres')->insertGetId([
                    'nom' => 'Français',
                    'code' => 'FR-DEMO',
                    'categorie_matiere_id' => $categorieId,
                    'coefficient' => 4,
                    'coefficient_defaut' => 4,
                    'actif' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Offres pour 6e A et 6e B
            $offresConfig = [
                'math_6a' => ['matiere_id' => $matiereMathId, 'classe_id' => $classe6eAId, 'vol' => 120, 'heures' => 5],
                'math_6b' => ['matiere_id' => $matiereMathId, 'classe_id' => $classe6eBId, 'vol' => 120, 'heures' => 5],
                'fr_6a' => ['matiere_id' => $matiereFrId, 'classe_id' => $classe6eAId, 'vol' => 120, 'heures' => 5],
                'fr_6b' => ['matiere_id' => $matiereFrId, 'classe_id' => $classe6eBId, 'vol' => 120, 'heures' => 5],
            ];

            $offresIds = [];
            foreach ($offresConfig as $key => $conf) {
                DB::table('offres_pedagogiques')->updateOrInsert(
                    [
                        'matiere_id' => $conf['matiere_id'],
                        'classe_id' => $conf['classe_id'],
                        'annee_scolaire_id' => $anneeId,
                    ],
                    [
                        'niveau_id' => 10,
                        'coefficient_matiere' => 4,
                        'volume_horaire' => $conf['vol'],
                        'heures_hebdomadaires' => $conf['heures'],
                        'actif' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
                $offresIds[$key] = (int) DB::table('offres_pedagogiques')
                    ->where('matiere_id', $conf['matiere_id'])
                    ->where('classe_id', $conf['classe_id'])
                    ->where('annee_scolaire_id', $anneeId)
                    ->value('id');
            }

            // Affectations pédagogiques (pour Secondaire Demo et pour AG)
            foreach ([$ensSecondaireId, $ensAdminId] as $ensId) {
                if (! $ensId) {
                    continue;
                }
                foreach ($offresIds as $key => $offreId) {
                    $conf = $offresConfig[$key];
                    DB::table('affectations_pedagogiques')->updateOrInsert(
                        [
                            'enseignant_id' => $ensId,
                            'matiere_id' => $conf['matiere_id'],
                            'classe_id' => $conf['classe_id'],
                            'annee_scolaire_id' => $anneeId,
                        ],
                        [
                            'offre_pedagogique_id' => $offreId,
                            'type_affectation' => 'matiere',
                            'actif' => true,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            // =========================================================================
            // 5. PROGRAMME ANNUEL DE MATHÉMATIQUES 6E (RÉFÉRENCE OFFICIELLE & VERSIONS)
            // =========================================================================
            $progMath = DB::table('programmes')->where('titre', 'Programme Annuel de Mathématiques 6e')->first();
            if (! $progMath) {
                $progMathId = DB::table('programmes')->insertGetId([
                    'matiere_id' => $matiereMathId,
                    'niveau_id' => 10,
                    'classe_id' => $classe6eAId,
                    'offre_pedagogique_id' => $offresIds['math_6a'],
                    'annee_scolaire_id' => $anneeId,
                    'source' => 'enseignant',
                    'enseignant_id' => $ensSecondaireId ?: $ensAdminId,
                    'titre' => 'Programme Annuel de Mathématiques 6e',
                    'description' => 'Programme officiel conforme aux référentiels : arithmétique, géométrie et grandeurs.',
                    'statut' => StatutProgrammeEnum::VALIDE->value,
                    'version' => 1,
                    'date_validation' => '2026-09-02 10:00:00',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $progMathId = $progMath->id;
                DB::table('programmes')->where('id', $progMathId)->update([
                    'statut' => StatutProgrammeEnum::VALIDE->value,
                    'version' => 1,
                ]);
            }

            // Lier ce programme aux offres 6e A et 6e B
            DB::table('offres_pedagogiques')->whereIn('id', [$offresIds['math_6a'], $offresIds['math_6b']])
                ->update(['programme_id' => $progMathId]);

            // Chapitres et leçons
            $chapitresData = [
                [
                    'titre' => 'Chapitre 1 : Nombres et calculs numériques',
                    'ordre' => 1,
                    'vol' => 20.0,
                    'lecons' => [
                        ['titre' => '1.1 Les nombres entiers naturels et numération', 'vol' => 3.0],
                        ['titre' => '1.2 Les nombres décimaux et repérage sur demi-droite', 'vol' => 3.0],
                        ['titre' => '1.3 Addition et soustraction des décimaux', 'vol' => 4.0],
                        ['titre' => '1.4 Multiplication des nombres décimaux', 'vol' => 4.0],
                        ['titre' => '1.5 Division euclidienne et partage équitable', 'vol' => 3.0],
                        ['titre' => '1.6 Division décimale et valeurs approchées', 'vol' => 3.0],
                    ],
                ],
                [
                    'titre' => 'Chapitre 2 : Écritures fractionnaires et quotients',
                    'ordre' => 2,
                    'vol' => 15.0,
                    'lecons' => [
                        ['titre' => '2.1 Définition du quotient et fraction partage', 'vol' => 4.0],
                        ['titre' => '2.2 Fractions égales et simplification', 'vol' => 4.0],
                        ['titre' => '2.3 Prendre une fraction d\'une quantité', 'vol' => 4.0],
                        ['titre' => '2.4 Pourcentages et échelles', 'vol' => 3.0],
                    ],
                ],
                [
                    'titre' => 'Chapitre 3 : Géométrie plane et figures usuelles',
                    'ordre' => 3,
                    'vol' => 20.0,
                    'lecons' => [
                        ['titre' => '3.1 Droites, segments et demi-droites', 'vol' => 3.0],
                        ['titre' => '3.2 Droites perpendiculaires et parallèles (équerre et règle)', 'vol' => 4.0],
                        ['titre' => '3.3 Le cercle et les polygones usuels', 'vol' => 4.0],
                        ['titre' => '3.4 Triangles particuliers (isocèle, équilatéral, rectangle)', 'vol' => 5.0],
                        ['titre' => '3.5 Quadrilatères particuliers (losange, rectangle, carré)', 'vol' => 4.0],
                    ],
                ],
                [
                    'titre' => 'Chapitre 4 : Grandeurs, mesures, périmètres et aires',
                    'ordre' => 4,
                    'vol' => 18.0,
                    'lecons' => [
                        ['titre' => '4.1 Unités de longueur et calcul de périmètres', 'vol' => 4.0],
                        ['titre' => '4.2 Notion d\'aire et unités de surface', 'vol' => 4.0],
                        ['titre' => '4.3 Aire du rectangle et du triangle rectangle', 'vol' => 5.0],
                        ['titre' => '4.4 Volumes du pavé droit et contenances', 'vol' => 5.0],
                    ],
                ],
            ];

            $allLecons = [];
            foreach ($chapitresData as $cData) {
                $chap = DB::table('programme_chapitres')
                    ->where('programme_id', $progMathId)
                    ->where('titre', $cData['titre'])
                    ->first();

                if (! $chap) {
                    $chapId = DB::table('programme_chapitres')->insertGetId([
                        'programme_id' => $progMathId,
                        'titre' => $cData['titre'],
                        'type_structure' => 'chapitre',
                        'volume_horaire_prevu' => $cData['vol'],
                        'ordre' => $cData['ordre'],
                        'statut' => 'en_cours',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $chapId = $chap->id;
                }

                foreach ($cData['lecons'] as $idx => $lData) {
                    $lecon = DB::table('programme_lecons')
                        ->where('programme_chapitre_id', $chapId)
                        ->where('titre', $lData['titre'])
                        ->first();

                    if (! $lecon) {
                        $leconId = DB::table('programme_lecons')->insertGetId([
                            'programme_chapitre_id' => $chapId,
                            'titre' => $lData['titre'],
                            'type_element' => 'lecon',
                            'ordre' => $idx + 1,
                            'volume_horaire_prevu' => $lData['vol'],
                            'statut' => 'a_faire',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    } else {
                        $leconId = $lecon->id;
                    }
                    $allLecons[] = ['id' => $leconId, 'titre' => $lData['titre'], 'chapitre_id' => $chapId];
                }
            }

            // =========================================================================
            // 6. CRÉNEAUX HORAIRES DE LA SEMAINE
            // =========================================================================
            $creneauxConfig = [
                ['jour' => 1, 'debut' => '08:00', 'fin' => '10:00'], // Lundi matin 1
                ['jour' => 1, 'debut' => '10:00', 'fin' => '12:00'], // Lundi matin 2
                ['jour' => 2, 'debut' => '08:00', 'fin' => '10:00'], // Mardi matin 1
                ['jour' => 2, 'debut' => '10:00', 'fin' => '12:00'], // Mardi matin 2
                ['jour' => 4, 'debut' => '08:00', 'fin' => '10:00'], // Jeudi matin 1
                ['jour' => 4, 'debut' => '10:00', 'fin' => '12:00'], // Jeudi matin 2
                ['jour' => 5, 'debut' => '08:00', 'fin' => '10:00'], // Vendredi matin 1
                ['jour' => 5, 'debut' => '10:00', 'fin' => '12:00'], // Vendredi matin 2
            ];

            $creneauxIds = [];
            foreach ($creneauxConfig as $c) {
                $creneau = DB::table('creneaux_horaires')
                    ->where('jour_semaine', $c['jour'])
                    ->where('heure_debut', $c['debut'])
                    ->where('heure_fin', $c['fin'])
                    ->first();

                if (! $creneau) {
                    $cId = DB::table('creneaux_horaires')->insertGetId([
                        'jour_semaine' => $c['jour'],
                        'heure_debut' => $c['debut'],
                        'heure_fin' => $c['fin'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $cId = $creneau->id;
                }
                $creneauxIds["{$c['jour']}_{$c['debut']}"] = $cId;
            }

            // =========================================================================
            // 7. EMPLOIS DU TEMPS
            // =========================================================================
            // Cours Maths 6e A : Salle 101, Lundi 08h-10h et Jeudi 08h-10h
            $edtMath6A_1 = DB::table('emplois_du_temps')->updateOrInsert(
                [
                    'classe_id' => $classe6eAId,
                    'matiere_id' => $matiereMathId,
                    'annee_scolaire_id' => $anneeId,
                    'creneau_id' => $creneauxIds['1_08:00'],
                ],
                [
                    'enseignant_id' => $ensSecondaireId ?: $ensAdminId,
                    'salle_id' => $sallesIds['S101'],
                    'actif' => true,
                    'statut' => 'publie',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $edtMath6A_1_Id = DB::table('emplois_du_temps')
                ->where('classe_id', $classe6eAId)
                ->where('creneau_id', $creneauxIds['1_08:00'])
                ->value('id');

            $edtMath6A_2 = DB::table('emplois_du_temps')->updateOrInsert(
                [
                    'classe_id' => $classe6eAId,
                    'matiere_id' => $matiereMathId,
                    'annee_scolaire_id' => $anneeId,
                    'creneau_id' => $creneauxIds['4_08:00'],
                ],
                [
                    'enseignant_id' => $ensSecondaireId ?: $ensAdminId,
                    'salle_id' => $sallesIds['S101'],
                    'actif' => true,
                    'statut' => 'publie',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $edtMath6A_2_Id = DB::table('emplois_du_temps')
                ->where('classe_id', $classe6eAId)
                ->where('creneau_id', $creneauxIds['4_08:00'])
                ->value('id');

            // Cours Maths 6e B : Salle 102, Mardi 08h-10h et Vendredi 08h-10h
            $edtMath6B_1 = DB::table('emplois_du_temps')->updateOrInsert(
                [
                    'classe_id' => $classe6eBId,
                    'matiere_id' => $matiereMathId,
                    'annee_scolaire_id' => $anneeId,
                    'creneau_id' => $creneauxIds['2_08:00'],
                ],
                [
                    'enseignant_id' => $ensSecondaireId ?: $ensAdminId,
                    'salle_id' => $sallesIds['S102'],
                    'actif' => true,
                    'statut' => 'publie',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $edtMath6B_1_Id = DB::table('emplois_du_temps')
                ->where('classe_id', $classe6eBId)
                ->where('creneau_id', $creneauxIds['2_08:00'])
                ->value('id');

            // =========================================================================
            // 8. SÉANCES & PROGRESSIONS RÉELLES
            // =========================================================================
            $teacherId = $ensSecondaireId ?: $ensAdminId;

            // --- CLASSE 6e A : RYTHME RÉGULIER & AVANCE ---
            // Séance 1 (Lundi 08 Septembre 2026) : DISPENSÉE, Progression renseignée
            $sA1_Id = DB::table('seances')->insertGetId([
                'emploi_du_temps_id' => $edtMath6A_1_Id,
                'date_seance' => '2026-09-07',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S101'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => true,
                'contenu_enseigne' => 'Introduction générale, rappels sur les grands nombres entiers et système de numération décimale.',
                'devoirs_donnes' => 'Exercices 1, 2 et 3 page 14.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('progressions')->insert([
                'seance_id' => $sA1_Id,
                'chapitre_id' => $allLecons[0]['chapitre_id'],
                'lecon_id' => $allLecons[0]['id'],
                'contenu_couvert' => 'Numération décimale et écriture en chiffres et lettres.',
                'chapitre_termine' => false,
                'statut_element' => StatutProgressionElementEnum::TERMINEE->value,
                'duree_minutes' => 120,
                'date_progression' => '2026-09-07',
                'devoirs_donnes' => 'Exercices 1, 2 et 3 page 14.',
                'observation' => 'Très bonne participation de la classe de 6e A.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('devoirs')->insert([
                'seance_id' => $sA1_Id,
                'titre' => 'Exercices numération décimale',
                'description' => 'Faire les exercices 1, 2 et 3 page 14 du manuel.',
                'type_devoir' => TypeDevoirEnum::EXERCICE->value,
                'date_limite' => '2026-09-10 08:00:00',
                'donne_par' => $teacherId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Séance 2 (Jeudi 10 Septembre 2026) : DISPENSÉE, Progression renseignée
            $sA2_Id = DB::table('seances')->insertGetId([
                'emploi_du_temps_id' => $edtMath6A_2_Id,
                'date_seance' => '2026-09-10',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S101'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => true,
                'contenu_enseigne' => 'Repérage sur une demi-droite graduée et comparaison des décimaux.',
                'devoirs_donnes' => 'DM n°1 à rendre pour le 17 septembre.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('progressions')->insert([
                'seance_id' => $sA2_Id,
                'chapitre_id' => $allLecons[1]['chapitre_id'],
                'lecon_id' => $allLecons[1]['id'],
                'contenu_couvert' => 'Repérage des décimaux sur demi-droite graduée.',
                'chapitre_termine' => false,
                'statut_element' => StatutProgressionElementEnum::TERMINEE->value,
                'duree_minutes' => 120,
                'date_progression' => '2026-09-10',
                'devoirs_donnes' => 'DM n°1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('devoirs')->insert([
                'seance_id' => $sA2_Id,
                'titre' => 'Devoir Maison n°1 : Repérage et décimaux',
                'description' => 'Rédiger proprement sur feuille double les exercices 5, 8 et 12 p. 18.',
                'type_devoir' => TypeDevoirEnum::EXERCICE->value,
                'date_limite' => '2026-09-17 08:00:00',
                'donne_par' => $teacherId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Séance 3 (Lundi 14 Septembre 2026) : DISPENSÉE, Progression renseignée
            $sA3_Id = DB::table('seances')->insertGetId([
                'emploi_du_temps_id' => $edtMath6A_1_Id,
                'date_seance' => '2026-09-14',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S101'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => true,
                'contenu_enseigne' => 'Addition et soustraction des décimaux, techniques opératoires.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('progressions')->insert([
                'seance_id' => $sA3_Id,
                'chapitre_id' => $allLecons[2]['chapitre_id'],
                'lecon_id' => $allLecons[2]['id'],
                'contenu_couvert' => 'Calculs posés et calcul mental avec les nombres décimaux.',
                'chapitre_termine' => false,
                'statut_element' => StatutProgressionElementEnum::TERMINEE->value,
                'duree_minutes' => 120,
                'date_progression' => '2026-09-14',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Séance 4 (Jeudi 17 Septembre 2026) : DISPENSÉE, Progression renseignée
            $sA4_Id = DB::table('seances')->insertGetId([
                'emploi_du_temps_id' => $edtMath6A_2_Id,
                'date_seance' => '2026-09-17',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S101'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => true,
                'contenu_enseigne' => 'Multiplication des décimaux et ordre de grandeur.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('progressions')->insert([
                'seance_id' => $sA4_Id,
                'chapitre_id' => $allLecons[3]['chapitre_id'],
                'lecon_id' => $allLecons[3]['id'],
                'contenu_couvert' => 'Multiplication de deux nombres décimaux.',
                'chapitre_termine' => false,
                'statut_element' => StatutProgressionElementEnum::TERMINEE->value,
                'duree_minutes' => 120,
                'date_progression' => '2026-09-17',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Séance 5 (Lundi 21 Septembre 2026) : DISPENSÉE MAIS PROGRESSION NON ENCORE RENSEIGNÉE !
            // -> Permet à l'utilisateur de tester directement la saisie sur l'UI !
            DB::table('seances')->insert([
                'emploi_du_temps_id' => $edtMath6A_1_Id,
                'date_seance' => '2026-09-21',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S101'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => false,
                'contenu_enseigne' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Séance 6 (Séance future) : PROGRAMMÉE
            DB::table('seances')->insert([
                'emploi_du_temps_id' => $edtMath6A_2_Id,
                'date_seance' => Carbon::now()->addDays(2)->format('Y-m-d'),
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S101'],
                'duree_minutes' => 120,
                'statut' => 'programmee',
                'progression_renseignee' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // --- CLASSE 6e B : RYTHME PLUS LENT (DÉCLENCHE ALERTE RETARD) ---
            // Séance 1 (Mardi 08 Septembre 2026) : DISPENSÉE, 1 seule leçon terminée
            $sB1_Id = DB::table('seances')->insertGetId([
                'emploi_du_temps_id' => $edtMath6B_1_Id,
                'date_seance' => '2026-09-08',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S102'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => true,
                'contenu_enseigne' => 'Prise de contact et révisions des nombres entiers.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('progressions')->insert([
                'seance_id' => $sB1_Id,
                'chapitre_id' => $allLecons[0]['chapitre_id'],
                'lecon_id' => $allLecons[0]['id'],
                'contenu_couvert' => 'Révisions des opérations de base sur les entiers.',
                'chapitre_termine' => false,
                'statut_element' => StatutProgressionElementEnum::TERMINEE->value,
                'duree_minutes' => 120,
                'date_progression' => '2026-09-08',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Séance 2 (Mardi 15 Septembre 2026) : DISPENSÉE, Leçon commencée seulement
            $sB2_Id = DB::table('seances')->insertGetId([
                'emploi_du_temps_id' => $edtMath6B_1_Id,
                'date_seance' => '2026-09-15',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S102'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => true,
                'contenu_enseigne' => 'Découverte des nombres décimaux.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('progressions')->insert([
                'seance_id' => $sB2_Id,
                'chapitre_id' => $allLecons[1]['chapitre_id'],
                'lecon_id' => $allLecons[1]['id'],
                'contenu_couvert' => 'Début de la notion de décimaux.',
                'chapitre_termine' => false,
                'statut_element' => StatutProgressionElementEnum::COMMENCEE->value,
                'duree_minutes' => 60,
                'date_progression' => '2026-09-15',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Séance 3 (Mardi 22 Septembre 2026) : DISPENSÉE MAIS EN ATTENTE DE SAISIE DE PROGRESSION
            DB::table('seances')->insert([
                'emploi_du_temps_id' => $edtMath6B_1_Id,
                'date_seance' => '2026-09-22',
                'enseignant_id' => $teacherId,
                'enseignant_prevu_id' => $teacherId,
                'enseignant_reel_id' => $teacherId,
                'salle_id' => $sallesIds['S102'],
                'duree_minutes' => 120,
                'statut' => 'dispensee',
                'progression_renseignee' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
