<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $maintenant = now();
        $nouvellesFonctionnalites = [
            // Socle / Administration
            ['code' => 'socle.utilisateurs', 'nom' => 'Gestion des utilisateurs', 'description' => 'Création, modification et blocage des comptes', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 11],
            ['code' => 'socle.roles', 'nom' => 'Gestion des rôles & permissions', 'description' => 'Attribution des rôles et autorisations Spatie', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 12],
            ['code' => 'socle.audit', 'nom' => 'Journal d\'audit & sécurité', 'description' => 'Consultation des journaux d\'activité et d\'audit', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 13],
            ['code' => 'socle.parametres', 'nom' => 'Paramétrage général', 'description' => 'Formats de numérotation, seuils et jours fériés', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 14],

            // RH
            ['code' => 'rh.employes', 'nom' => 'Gestion du personnel', 'description' => 'Fiches employés, contrats et dossiers', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 21],
            ['code' => 'rh.enseignants', 'nom' => 'Gestion des enseignants', 'description' => 'Affectations et spécialités des enseignants', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 22],
            ['code' => 'rh.conges', 'nom' => 'Gestion des congés', 'description' => 'Demandes, validations et soldes de congés', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 23],
            ['code' => 'rh.paie', 'nom' => 'Gestion de la paie', 'description' => 'Fiches de paie et rémunérations', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 24],

            // Scolarité
            ['code' => 'scolarite.eleves', 'nom' => 'Fiches élèves & dossier', 'description' => 'Informations personnelles et parcours des élèves', 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 31],
            ['code' => 'scolarite.inscriptions', 'nom' => 'Inscriptions & réinscriptions', 'description' => 'Gestion du processus d\'inscription', 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 32],
            ['code' => 'scolarite.classes', 'nom' => 'Niveaux & classes', 'description' => 'Organisation des niveaux, séries et classes', 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 33],

            // Pédagogie
            ['code' => 'pedagogie.programmes', 'nom' => 'Programmes & matières', 'description' => 'Gestion des matières et coefficients', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 41],
            ['code' => 'pedagogie.seances', 'nom' => 'Séances & cahier de texte', 'description' => 'Suivi des séances et devoirs', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 42],
            ['code' => 'pedagogie.evaluations', 'nom' => 'Notes & évaluations', 'description' => 'Saisie des notes et calcul des moyennes', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 43],

            // Finances
            ['code' => 'finances.factures', 'nom' => 'Factures & préinscriptions', 'description' => 'Émission et suivi des factures', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 51],
            ['code' => 'finances.paiements', 'nom' => 'Paiements & règlements', 'description' => 'Enregistrement des encaissements', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 52],
            ['code' => 'finances.caisse', 'nom' => 'Gestion de caisse', 'description' => 'Entrées, sorties et journaux de caisse', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 53],
            ['code' => 'finances.budget', 'nom' => 'Budget & seuils', 'description' => 'Suivi des lignes budgétaires', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 54],

            // Communication
            ['code' => 'communication.annonces', 'nom' => 'Annonces & communications', 'description' => 'Publication des messages d\'information', 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 61],
            ['code' => 'communication.rendezvous', 'nom' => 'Prise de rendez-vous', 'description' => 'Planification des rencontres parents/direction', 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 62],
            ['code' => 'communication.notifications', 'nom' => 'Notifications & SMS', 'description' => 'Envoi des alertes et rappels', 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 63],

            // Vie Scolaire / Assiduité
            ['code' => 'assiduite.pointage', 'nom' => 'Pointage & présences', 'description' => 'Saisie des retards et absences', 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 71],
            ['code' => 'assiduite.reclamations', 'nom' => 'Réclamations & incidents', 'description' => 'Suivi des signalements et sanctions', 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 72],
            ['code' => 'viescolaire.visiteurs', 'nom' => 'Visiteurs & Infirmerie', 'description' => 'Registre des visites et passages santé', 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 73],

            // Logistique
            ['code' => 'logistique.salles', 'nom' => 'Salles & équipements', 'description' => 'Gestion des locaux et matériels', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 81],
            ['code' => 'logistique.evenements', 'nom' => 'Événements & réservations', 'description' => 'Organisation des manifestations scolaires', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 82],
        ];

        foreach ($nouvellesFonctionnalites as $item) {
            DB::table('catalogue_fonctionnalites')->updateOrInsert(
                ['code' => $item['code']],
                [
                    ...$item,
                    'actif' => true,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ]
            );
        }

        // Accorder ces nouvelles fonctionnalités aux rôles staff par défaut
        $roles = DB::table('roles')->whereIn('name', ['Directeur', 'Enseignant', 'Comptable', 'SurveillantGeneral', 'ChargeLogistique'])->pluck('id');
        $fonctionnalitesIds = DB::table('catalogue_fonctionnalites')->whereIn('code', array_column($nouvellesFonctionnalites, 'code'))->pluck('id');

        foreach ($roles as $roleId) {
            foreach ($fonctionnalitesIds as $fonctionnaliteId) {
                DB::table('habilitations_roles_fonctionnalites')->updateOrInsert(
                    ['role_id' => $roleId, 'fonctionnalite_id' => $fonctionnaliteId],
                    ['actif' => true, 'created_at' => $maintenant, 'updated_at' => $maintenant]
                );
            }
        }
    }

    public function down(): void
    {
        $codes = [
            'socle.utilisateurs', 'socle.roles', 'socle.audit', 'socle.parametres',
            'rh.employes', 'rh.enseignants', 'rh.conges', 'rh.paie',
            'scolarite.eleves', 'scolarite.inscriptions', 'scolarite.classes',
            'pedagogie.programmes', 'pedagogie.seances', 'pedagogie.evaluations',
            'finances.factures', 'finances.paiements', 'finances.caisse', 'finances.budget',
            'communication.annonces', 'communication.rendezvous', 'communication.notifications',
            'assiduite.pointage', 'assiduite.reclamations', 'viescolaire.visiteurs',
            'logistique.salles', 'logistique.evenements',
        ];

        DB::table('catalogue_fonctionnalites')->whereIn('code', $codes)->delete();
    }
};
