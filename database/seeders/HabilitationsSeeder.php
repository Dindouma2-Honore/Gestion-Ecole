<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Socle\Contracts\HabilitationServiceContract;
use Illuminate\Database\Seeder;

class HabilitationsSeeder extends Seeder
{
    public function run(): void
    {
        app(HabilitationServiceContract::class)->synchroniserCatalogue([
            ['code' => 'module.socle', 'nom' => 'Administration générale', 'description' => null, 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 10, 'actif' => true],
            ['code' => 'socle.utilisateurs', 'nom' => 'Gestion des utilisateurs', 'description' => 'Création, modification et blocage des comptes', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 11, 'actif' => true],
            ['code' => 'socle.roles', 'nom' => 'Gestion des rôles & permissions', 'description' => 'Attribution des rôles et autorisations Spatie', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 12, 'actif' => true],
            ['code' => 'socle.audit', 'nom' => 'Journal d\'audit & sécurité', 'description' => 'Consultation des journaux d\'activité', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 13, 'actif' => true],
            ['code' => 'socle.parametres', 'nom' => 'Paramétrage général', 'description' => 'Formats de numérotation, seuils et jours fériés', 'categorie' => 'Administration', 'module_technique' => 'Socle', 'ordre' => 14, 'actif' => true],

            ['code' => 'module.rh', 'nom' => 'Ressources humaines', 'description' => null, 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 20, 'actif' => true],
            ['code' => 'rh.employes', 'nom' => 'Gestion du personnel', 'description' => 'Fiches employés, contrats et dossiers', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 21, 'actif' => true],
            ['code' => 'rh.enseignants', 'nom' => 'Gestion des enseignants', 'description' => 'Affectations et spécialités', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 22, 'actif' => true],
            ['code' => 'rh.conges', 'nom' => 'Gestion des congés', 'description' => 'Demandes, validations et soldes', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 23, 'actif' => true],
            ['code' => 'rh.paie', 'nom' => 'Gestion de la paie', 'description' => 'Fiches de paie et rémunérations', 'categorie' => 'RH', 'module_technique' => 'RH', 'ordre' => 24, 'actif' => true],

            ['code' => 'module.scolarite', 'nom' => 'Scolarité et inscriptions', 'description' => null, 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 30, 'actif' => true],
            ['code' => 'scolarite.eleves', 'nom' => 'Fiches élèves & dossier', 'description' => 'Informations personnelles des élèves', 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 31, 'actif' => true],
            ['code' => 'scolarite.inscriptions', 'nom' => 'Inscriptions & réinscriptions', 'description' => 'Processus d\'inscription', 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 32, 'actif' => true],
            ['code' => 'scolarite.classes', 'nom' => 'Niveaux & classes', 'description' => 'Niveaux, séries et classes', 'categorie' => 'Scolarité', 'module_technique' => 'Scolarite', 'ordre' => 33, 'actif' => true],

            ['code' => 'module.pedagogie', 'nom' => 'Pédagogie', 'description' => null, 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 40, 'actif' => true],
            ['code' => 'pedagogie.programmes', 'nom' => 'Programmes & matières', 'description' => 'Matières et coefficients', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 41, 'actif' => true],
            ['code' => 'pedagogie.seances', 'nom' => 'Séances & cahier de texte', 'description' => 'Séances et devoirs', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 42, 'actif' => true],
            ['code' => 'pedagogie.evaluations', 'nom' => 'Notes & évaluations', 'description' => 'Notes et moyennes', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 43, 'actif' => true],
            ['code' => 'pedagogie.discipline', 'nom' => 'Discipline des élèves', 'description' => 'Incidents, mesures et informations confidentielles', 'categorie' => 'Pédagogie', 'module_technique' => 'Pedagogie', 'ordre' => 44, 'actif' => true],

            ['code' => 'module.finances', 'nom' => 'Finances', 'description' => null, 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 50, 'actif' => true],
            ['code' => 'finances.factures', 'nom' => 'Factures & préinscriptions', 'description' => 'Émission et suivi des factures', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 51, 'actif' => true],
            ['code' => 'finances.paiements', 'nom' => 'Paiements & règlements', 'description' => 'Enregistrement des règlements', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 52, 'actif' => true],
            ['code' => 'finances.caisse', 'nom' => 'Gestion de caisse', 'description' => 'Mouvements de caisse', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 53, 'actif' => true],
            ['code' => 'finances.budget', 'nom' => 'Budget & seuils', 'description' => 'Lignes budgétaires', 'categorie' => 'Finances', 'module_technique' => 'Finances', 'ordre' => 54, 'actif' => true],

            ['code' => 'module.communication', 'nom' => 'Communication', 'description' => null, 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 60, 'actif' => true],
            ['code' => 'communication.annonces', 'nom' => 'Annonces & communications', 'description' => 'Messages d\'information', 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 61, 'actif' => true],
            ['code' => 'communication.rendezvous', 'nom' => 'Prise de rendez-vous', 'description' => 'Rencontres parents/direction', 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 62, 'actif' => true],
            ['code' => 'communication.notifications', 'nom' => 'Notifications & SMS', 'description' => 'Alertes et rappels', 'categorie' => 'Communication', 'module_technique' => 'Communication', 'ordre' => 63, 'actif' => true],

            ['code' => 'module.assiduite', 'nom' => 'Vie scolaire et assiduité', 'description' => null, 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 70, 'actif' => true],
            ['code' => 'assiduite.pointage', 'nom' => 'Pointage & présences', 'description' => 'Retards et absences', 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 71, 'actif' => true],
            ['code' => 'assiduite.reclamations', 'nom' => 'Réclamations & incidents', 'description' => 'Signalements et sanctions', 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 72, 'actif' => true],
            ['code' => 'viescolaire.visiteurs', 'nom' => 'Visiteurs & Infirmerie', 'description' => 'Registre des visites', 'categorie' => 'Vie scolaire', 'module_technique' => 'Assiduite', 'ordre' => 73, 'actif' => true],

            ['code' => 'module.viescolaire', 'nom' => 'Vie scolaire', 'description' => null, 'categorie' => 'Vie scolaire', 'module_technique' => 'VieScolaire', 'ordre' => 74, 'actif' => true],
            ['code' => 'viescolaire.sante', 'nom' => 'Santé & infirmerie', 'description' => 'Dossiers santé et visites', 'categorie' => 'Vie scolaire', 'module_technique' => 'VieScolaire', 'ordre' => 75, 'actif' => true],
            ['code' => 'viescolaire.sorties', 'nom' => 'Sorties des élèves', 'description' => 'Contrôle des sorties et personnes autorisées', 'categorie' => 'Vie scolaire', 'module_technique' => 'VieScolaire', 'ordre' => 76, 'actif' => true],

            ['code' => 'module.logistique', 'nom' => 'Logistique', 'description' => null, 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 80, 'actif' => true],
            ['code' => 'logistique.salles', 'nom' => 'Salles & équipements', 'description' => 'Gestion des locaux', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 81, 'actif' => true],
            ['code' => 'logistique.evenements', 'nom' => 'Événements & réservations', 'description' => 'Manifestations scolaires', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 82, 'actif' => true],
            ['code' => 'logistique.transport', 'nom' => 'Transport scolaire', 'description' => 'Circuits, véhicules et présences', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 83, 'actif' => true],
            ['code' => 'logistique.cantine', 'nom' => 'Cantine', 'description' => 'Menus, abonnements et présences', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 84, 'actif' => true],
            ['code' => 'logistique.bibliotheque', 'nom' => 'Bibliothèque', 'description' => 'Catalogue, emprunts et réservations', 'categorie' => 'Logistique', 'module_technique' => 'Logistique', 'ordre' => 85, 'actif' => true],

            ['code' => 'module.rapports', 'nom' => 'Rapports', 'description' => null, 'categorie' => 'Rapports', 'module_technique' => null, 'ordre' => 90, 'actif' => true],
        ]);
    }
}
